<?php require_once 'core/dbConfig.php'; ?>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <style>
    body {
      font-family: "Arial";
      background-image: url("https://img.freepik.com/free-vector/geometric-gradient-futuristic-background_23-2149116406.jpg?t=st=1748180042~exp=1748183642~hmac=67ba4bd3e24a59a3f4664631d962c751509cf7324caca771cca3f247391e5a71&w=1380");
      }
  </style>
  <title>Hello, world!</title>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8 p-5">
        <div class="card shadow">
          <div class="card-header">
            <h2>Welcome to GoogleDoclone! Register Now!</h2>
          </div>
          <form action="core/handleForms.php" method="POST">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="exampleInputEmail1">First Name</label>
                    <input type="text" class="form-control" name="first_name" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="exampleInputEmail1">Last Name</label>
                    <input type="text" class="form-control" name="last_name" required>
                  </div>
                </div>
              </div>
              <?php  
                if (isset($_SESSION['message']) && isset($_SESSION['status'])) {

                  if ($_SESSION['status'] == "200") {
                    echo "<h1 style='color: green;'>{$_SESSION['message']}</h1>";
                  }

                  else {
                    echo "<h1 style='color: red;'>{$_SESSION['message']}</h1>"; 
                  }

                }
                unset($_SESSION['message']);
                unset($_SESSION['status']);
              ?>
              <div class="form-group">
                <label for="exampleInputEmail1">Username</label>
                <input type="text" class="form-control" name="username" required>
              </div>
              <div class="form-group">
                <label for="exampleInputEmail1">Password</label>
                <input type="password" class="form-control" name="password" required>
              </div>
              <div class="form-group">
                <label for="exampleInputEmail1">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
                <input type="submit" class="btn btn-primary float-right mt-4" name="insertNewUserBtn">
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
