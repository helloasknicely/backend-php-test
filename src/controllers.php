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


$app->get('/todo/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $params = array();
    if ($id) {
        $sql = "SELECT * FROM `todos` WHERE `user_id` = ? AND id = ?";
        $params[] = $user['id'];
        $params[] = $id;
    } else {
        $sql = "SELECT * FROM `todos` WHERE `user_id` = ?";
        $params[] = $user['id'];
    }

    $query = $app['db']->executeQuery($sql, $params);
    $todos = $query->fetchAllAssociative();
    $todos = is_array($todos) ? $todos : [];

    foreach ($todos as &$todo) {
        if (empty($todo['status'])) {
            $todo['status'] = 'PROGRESS';
        }
    }

    if ($id) {
        return $app['twig']->render('todo.html', [
            'todo' => reset($todos),
        ]);
    }

    return $app['twig']->render('todos.html', [
            'todos' => $todos,
    ]);

})
->value('id', null);


$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    if (empty($description)) {
        $app['session']->getFlashBag()->add('descriptiontError', 'Please enter a Description');
        return $app->redirect('/todo');
    }

    $insert = $app['db']->executeStatement("
        INSERT INTO `todos` (`user_id`, `description`)
        VALUES (?, ?)
    ", array($user_id, $description));

    return $app->redirect('/todo');
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $delete = $app['db']->executeStatement("
        DELETE FROM `todos`
        WHERE `user_id` = ? AND `id` = ?
    ", array($user['id'], $id));

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

    $delete = $app['db']->executeStatement("
        UPDATE `todos`
        SET `status` = ?
        WHERE `user_id` = ? AND `id` = ?
    ", array($status, $user['id'], $id));

    return $app->redirect('/todo');
});