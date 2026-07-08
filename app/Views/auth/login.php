<!DOCTYPE html>
<html>

<head>
<title>IQwurksPunch Login</title>

<style>

body {
    font-family: Arial;
    background:#f2f2f2;
}

.box {
    width:350px;
    margin:100px auto;
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

<h2>Supervisor Login</h2>

<form method="post" action="/login">

<input name="username"
placeholder="Username"
required>

<input type="password"
name="password"
placeholder="Password"
required>

<button>
Login
</button>

</form>

</div>

</body>

</html>
