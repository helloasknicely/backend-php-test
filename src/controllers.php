<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if (empty($username) || empty($password)) {
        return $app['twig']->render('login.html', array());
    }

    $sql = "SELECT * FROM `users` WHERE `username` = ?";
    $query = $app['db']->executeQuery($sql, [$username]);
    $user = $query->fetchAssociative();

    if (!$user) {
        $app['session']->getFlashBag()->add('loginError', 'Invalid login credentials');
        return $app['twig']->render('login.html', array());
    }

    $valid = password_verify($password, $user['password']);

    if ($valid){
        $app['session']->set('user', $user);
        return $app->redirect('/todo');
    }

    return $app['twig']->render('login.html', array());
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}/{returntype}', function (Request $request, $id, $returntype) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    // Query builder for Todo items
    $params = array();
    $conditions = array();

    if ($id) {
        $conditions[] = "`user_id` = ?";
        $conditions[] = "`id` = ?";
        $params[] = $user['id'];
        $params[] = $id;

    } else {
        $conditions[] = "`user_id` = ?";
        $params[] = $user['id'];
    }

    // Pagination
    $items = $app['db']->executeQuery("
        SELECT `id`
        FROM `todos`
        WHERE `user_id` = ?
    ", array($user['id']))
    ->fetchAllAssociative();

    $numberoftodos = count($items);

    $pagesize = App\Entity\Todo::PAGESIZE;
    $totalpages = ceil($numberoftodos / $pagesize);
    $totalpages = ($totalpages) <= 0 ? 1 : $totalpages;

    $pagenumber = $request->query->get('p');
    $pagenumber = is_scalar($pagenumber) ? intval($pagenumber) : 1;
    $pagenumber = ($pagenumber <= 0 || $pagenumber > $totalpages) ? 1 : $pagenumber;

    $keys = array_map(function($i) {
        return $i['id'];
    }, $items);

    if ($pagenumber > 1 && $pagenumber <= $totalpages) {
        $index = $keys[($pagenumber - 1) * $pagesize];
        $conditions[] = "`id` >= ?";
        $params[] = $index;
    }

    // Query
    $sql = "
        SELECT *
        FROM `todos`
        WHERE " . implode(" AND ", $conditions) . "
        LIMIT {$pagesize}
    ";

    $query = $app['db']->executeQuery($sql, $params);
    $todos = $query->fetchAllAssociative();
    $todos = is_array($todos) ? $todos : [];

    // Response
    $pagedata = array(
        'todos' => $todos
    );

    if ($id) {
        $todo = reset($todos);

        if (empty($todo)) {
             return $app->redirect('/todo');
        }

        $pagedata = array(
            'todo' => $todo,
        );
    }

    $pagedata['totalpages'] = $totalpages;
    $pagedata['hasnext'] = ($pagenumber < $totalpages);
    $pagedata['hasprev'] = ($pagenumber > 1);
    $pagedata['currentpage'] = $pagenumber ?: 1;

    if ($returntype == 'json' || $request->query->get('json')) {
        $response = new Response(json_encode($pagedata));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    return $app['twig']->render($id ? 'todo.html' : 'todos.html', $pagedata);
})
->value('id', null)
->value('returntype', null);

$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    if (empty($description)) {
        if ($request->query->get('json')) {
            $response = new Response(json_encode([
                'status' => false,
                'message' => 'Please enter a Description'
            ]));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $app['session']->getFlashBag()->add('description', (object) ['added' => false, 'message' => 'Please enter a Description']);
        return $app->redirect('/todo');
    }

    $insert = $app['db']->executeStatement("
        INSERT INTO `todos` (`user_id`, `description`)
        VALUES (?, ?)
    ", array($user_id, $description));

    if ($request->query->get('json')) {
        $response = new Response(json_encode([
            'status' => true,
            'data' => (object) [
                'todo' => (object) [
                    'id' => intval($app['db']->lastInsertId()),
                    'description' => $description,
                    'status' => 'PROGRESS',
                    'user_id' => $user_id
                ]
            ]
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    $app['session']->getFlashBag()->add('todoMessage', 'Todo added');

    return $app->redirect('/todo');
});


$app->match('/todo/delete/{id}', function (Request $request, $id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $delete = $app['db']->executeStatement("
        DELETE FROM `todos`
        WHERE `user_id` = ? AND `id` = ?
    ", array($user['id'], $id));

    if ($request->query->get('json')) {
        $response = new Response(json_encode(['status' => true]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    $app['session']->getFlashBag()->add('todoMessage', 'Todo has been removed');

    return $app->redirect('/todo');
});


$app->match('/todo/status/{id}', function (Request $request, $id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $status = $request->get('status');

    if (empty(App\Entity\Todo::STATUSES[$status])) {
        return $app->redirect('/todo');
    }

    $update = $app['db']->executeStatement("
        UPDATE `todos`
        SET `status` = ?
        WHERE `user_id` = ? AND `id` = ?
    ", array($status, $user['id'], $id));

    if ($request->query->get('json')) {
        $response = new Response(json_encode(['status' => true]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    return $app->redirect('/todo');
});