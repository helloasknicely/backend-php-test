INSERT INTO users (username, password) VALUES
('user1', '$2y$10$rsCzKnV4RFGRchoECu98qeJNC8bFSv6555BbHJyvc2m9DyXSa9KaC'),
('user2', '$2y$10$6O.2ItyAP5oFjtXCWo.cIeJDQ6jQr4/AubSTyxovT6BxWfc2HS6Xy'),
('user3', '$2y$10$PTAbm.9.zS8l1iGvXOcKieUUARpzghqy.bG2hcASoq9M6aWN6LkNy');

INSERT INTO todos (user_id, description) VALUES
(1, 'Vivamus tempus'),
(1, 'lorem ac odio'),
(1, 'Ut congue odio'),
(1, 'Sodales finibus'),
(1, 'Accumsan nunc vitae'),
(2, 'Lorem ipsum'),
(2, 'In lacinia est'),
(2, 'Odio varius gravida');