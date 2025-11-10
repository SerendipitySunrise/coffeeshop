<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'yourgmail@gmail.com'; // your Gmail
    $mail->Password = 'your_app_password'; // App password (not Gmail password)
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('yourgmail@gmail.com', 'UNLI MAMI System');
    $mail->addAddress('receiver@gmail.com', 'Receiver Name');
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = '<h3>This is a test email from PHPMailer!</h3>';

    $mail->send();
    echo 'Email has been sent successfully!';
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
