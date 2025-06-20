<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <h1>Hello, {{ $name }}!</h1>
    <p>Please reset your password by clicking the link below:</p>
    <a href="{{ 'https://applymandu.com/reset-password/'.$token }}">Reset Password</a>
</body>
</html>