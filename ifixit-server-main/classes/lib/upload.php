<?php
$target_dir = $_POST['dir'];
if(empty($_FILES)) return "error, please try again later";
$target_file = $target_dir . basename($_POST['name'].'.jpg');
$uploadOk = 1;
$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
if(isset($_POST["submit"])) {
    $check = getimagesize($_FILES["fileUpload"]["tmp_name"]);
    if($check !== false) {
        echo "File is an image - " . $check["mime"] . ".";
        $uploadOk = 1;
    } else {
        echo "File is not an image.";
        $uploadOk = 0;
    }
}
if($imageFileType != "jpg") {
    echo "Sorry, only JPG files are allowed.";
    $uploadOk = 0;
}
if ($uploadOk == 0) {
    echo "Your file was not uploaded.";
} else {
    if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $target_file)) {
        echo "Success";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}
?>