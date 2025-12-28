<?php
/**
 * Contact Form Handler
 * Sends contact form submissions to support@forgefunding.org
 */

// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$privacy_agreed = isset($_POST['i_agree_privacy_policy']) ? true : false;

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

if (empty($message)) {
    $errors[] = 'Message is required';
}

if (!$privacy_agreed) {
    $errors[] = 'You must agree to the privacy policy';
}

// If there are errors, return JSON error response
if (!empty($errors)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Email configuration
$to = 'support@forgefunding.org';
$subject = 'New Contact Form Submission - Forge Funding';
$from_email = $email;
$from_name = $name;

// Email headers
$headers = [
    'From: ' . $from_name . ' <' . $from_email . '>',
    'Reply-To: ' . $from_email,
    'X-Mailer: PHP/' . phpversion(),
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8'
];

// Email body
$email_body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #47763b; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #47763b; }
        .value { margin-top: 5px; padding: 10px; background: white; border-left: 3px solid #47763b; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Contact Form Submission</h2>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>Name:</div>
                <div class='value'>{$name}</div>
            </div>
            <div class='field'>
                <div class='label'>Email:</div>
                <div class='value'>{$email}</div>
            </div>
            <div class='field'>
                <div class='label'>Message:</div>
                <div class='value'>" . nl2br($message) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Submitted:</div>
                <div class='value'>" . date('F j, Y, g:i a') . "</div>
            </div>
        </div>
    </div>
</body>
</html>
";

// Send email
$mail_sent = mail($to, $subject, $email_body, implode("\r\n", $headers));

// Return JSON response
header('Content-Type: application/json');

if ($mail_sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for contacting us! We will get back to you soon.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error sending your message. Please try again later.'
    ]);
}

