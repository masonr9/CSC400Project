<?php

  $database = mysqli_connect("localhost","root","","library_management"); 
  if (!$database) {
    die("DB connection failed: " . mysqli_connect_error());
  }
?>