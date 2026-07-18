<?php
require 'vendor/autoload.php'; // Composer autoload for AWS SDK

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// RDS connection using environment variables
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME'),
    getenv('DB_PORT')
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Configure S3 client using environment variables
$s3 = new S3Client([
    'region'  => getenv('AWS_REGION'),
    'version' => 'latest',
    'credentials' => [
        'key'    => getenv('AWS_ACCESS_KEY'),
        'secret' => getenv('AWS_SECRET_KEY'),
    ]
]);

$bucket = getenv('AWS_BUCKET');

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $age        = $_POST['age'];
    $location   = $_POST['location'];
    $photo_url  = "";

    // Upload photo to S3 if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $fileName = uniqid() . "-" . basename($_FILES['photo']['name']);
        try {
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key'    => $fileName,
                'SourceFile' => $_FILES['photo']['tmp_name']
                
            ]);
            $photo_url = $result['ObjectURL']; // S3 URL
        } catch (AwsException $e) {
            echo "<p style='color:red;'>S3 Upload Error: " . $e->getMessage() . "</p>";
        }
    }

    // Insert record into RDS
    $sql = "INSERT INTO users (first_name, last_name, age, location, photo_url)
            VALUES ('$first_name', '$last_name', '$age', '$location', '$photo_url')";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green;'>New record created successfully!</p>";
    } else {
        echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Records</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { margin-bottom: 30px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        img { max-width: 80px; }
    </style>
</head>
<body>

<h2>Add User (On Another Server)</h2>
<form method="POST" action="" enctype="multipart/form-data">
    First Name: <input type="text" name="first_name" required><br><br>
    Last Name: <input type="text" name="last_name" required><br><br>
    Age: <input type="number" name="age"><br><br>
    Location: <input type="text" name="location"><br><br>
    Photo: <input type="file" name="photo"><br><br>
    <input type="submit" value="Save">
</form>

<h2>Saved Users</h2>
<table>
    <tr>
        <th>ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Age</th>
        <th>Location</th>
        <th>Photo</th>
    </tr>
    <?php
    $result = $conn->query("SELECT * FROM users");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".$row['id']."</td>
                    <td>".$row['first_name']."</td>
                    <td>".$row['last_name']."</td>
                    <td>".$row['age']."</td>
                    <td>".$row['location']."</td>
                    <td>";
            if (!empty($row['photo_url'])) {
                echo "<img src='".$row['photo_url']."' alt='photo'>";
            } else {
                echo "No photo";
            }
            echo "</td></tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No records found</td></tr>";
    }
    ?>
</table>

</body>
</html>
