<!DOCTYPE html>
<html>

<head>
    <title>IQwurksPunch Setup</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background:#f2f2f2;
        }

        .box {
            width:400px;
            margin:80px auto;
            background:white;
            padding:30px;
            border-radius:8px;
        }

        input {
            width:100%;
            padding:10px;
            margin:8px 0;
        }

        button {
            width:100%;
            padding:12px;
        }
    </style>
</head>

<body>

<div class="box">

<h1>IQwurksPunch</h1>

<h2>Initial Setup</h2>

<form method="post" action="/setup">

<input name="username"
       placeholder="Username"
       required>

<input name="email"
       placeholder="Email"
       required>

<input type="password"
       name="password"
       placeholder="Password"
       required>

<input type="password"
       name="confirm"
       placeholder="Confirm Password"
       required>

<button>
Create Administrator
</button>

</form>

</div>

</body>

</html>
