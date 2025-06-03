<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Başarılı</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to right, #ffecd2, #fcb69f);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #ff6f61;
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        p {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 30px;
        }

        a {
            text-decoration: none;
            color: #fff;
            background-color: #ff6f61;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        a:hover {
            background-color: #e65c50;
        }

        .btn-primary {
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Siparişiniz Başarıyla Alındı!</h1>
        <p>Sipariş detaylarınızı <a href="customer_orders.php">sipariş Detay</a> sayfasından görüntüleyebilirsiniz.</p>
        <a href="../index.php" class="btn btn-primary">Alışverişe Devam Et</a>
    </div>
</body>
</html>