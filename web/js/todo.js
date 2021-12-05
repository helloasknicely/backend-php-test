var app = new Vue({
    el: '#app',
    delimiters: ['${', '}'],
    data: {
        todos: [],
        totalPages: 1,
        hasNext: false,
        hasPrev: false,
        currentPage: 1,
        statusOptions: [
            { "code": "PROGRESS", "label": "In Progress" },
            { "code": "COMPLETE", "label": "Completed" },
        ],
        alertMessage: null,
        todoDescription: null,
        todoError: null,
        tooltipTimeOut: null,
    },
    methods: {
        loadData() {
            const self = this;
            $.get('/todo?json=1', function(response) {
                self.todos = response.todos || [];
                self.totalPages = response.totalPages || 1;
                self.hasNext = response.hasnext || false;
                self.hasPrev = response.hasprev || false;
                self.currentPage = response.currentpage || 1;
            });
        },
        todoBtnClass(todo) {
            if (todo.status && todo.status == 'COMPLETE') {
                return 'btn-success';
            }

            return 'btn-info';
        },
        todoToolTipClass(todo) {
            if (!todo.state) {
                return '';
            }
            switch (todo.state) {
                case 'added':
                    return 'todoItemAdded';
                case 'deleted':
                    return 'todoItemDeleted';
                default:
                    return '';
            }
        },
        removeTodo(id) {
            if (!id) {
                return false;
            }

            const self = this;
            $.post(`/todo/delete/${id}?json=1`, function(response) {
                let index = self.todos.findIndex((t => t.id == id));
                self.$set(self.todos[index], 'state', 'deleted');
                self.todos = self.todos.filter(function(todo) {
                    return todo.id !== id;
                });
            });
        },
        addTodo() {
            const self = this;
            self.todoError = null;

            if (!self.todoDescription) {
                self.todoError = 'Please enter a Description';
                return false;
            }
            $.post(`/todo/add?json=1`, {"description": self.todoDescription}, function(response) {
                if (response && response.data && response.data.todo || false) {
                    self.todoDescription = null;
                    self.todos.push(response.data.todo);

                    let index = self.todos.findIndex((t => t.id == response.data.todo.id));
                    self.$set(self.todos[index], 'state', 'added');

                    Vue.nextTick().then(function () {
                        clearTimeout(self.tooltipTimeOut);
                        self.tooltipTimeOut = setTimeout(function () {
                            $('#todo' + response.data.todo.id).tooltip({
                                trigger: 'manual',
                                placement: 'right',
                                title: 'added',
                            }).tooltip('show');
                        }, 300);
                    });
                }
                else {
                    if (response.message || false) {
                        self.todoError = response.message;
                    }
                    self.todoError = 'Something has gone wrong';
                }
            });
        },
        changeStatus(id, code) {
            if (!id || !code) {
                return false;
            }
            const self = this;
            $.post(`/todo/status/${id}?json=1`, {"status": code}, function(response) {
                if (response && response.status) {
                    let index = self.todos.findIndex((t => t.id == id));
                    self.todos[index].status = code;
                }
            });
        },
    },
    mounted() {
        this.loadData();
        $(document).on('shown.bs.tooltip', function (e) {
            setTimeout(function () {
                $(e.target).tooltip('hide');
            }, 1000);
        });
    }
});