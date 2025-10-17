<?php
require __DIR__ . '/includes/app.php';

$result = rb_mail_send([
    'to' => 'rakeshgiree999@gmail.com',
    'subject' => 'Test message',
    'html' => '<p>Hello from PHPMailer!</p>',
    'text' => 'Hello from PHPMailer!',
]);

var_dump($result);
