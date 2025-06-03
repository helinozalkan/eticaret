
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>E-Ticaret Ekibi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .team-card {
      background-color: #ffffff;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .team-card h5 {
      color: #0d6efd;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<div class="container py-5">
  <h2 class="text-center text-primary mb-5">E-Ticaret Ekibi</h2>

  <?php
    $ekip = [
      ["isim" => "Ayşe Güneş", "ozgecmis" => "Ürün yönetimi ve dijital pazarlama konularında 5 yıllık deneyime sahip."],
      ["isim" => "Mehmet Toprak", "ozgecmis" => "Stok yönetimi ve tedarik zinciri optimizasyonu uzmanı."],
      ["isim" => "Zeynep Aksoy", "ozgecmis" => "UI/UX tasarımcı olarak e-ticaret deneyimini kullanıcı dostu hale getiriyor."],
      ["isim" => "Burak Kılıç", "ozgecmis" => "Ödeme sistemleri ve güvenli alışveriş altyapıları üzerine çalışıyor."],
      ["isim" => "Gamze Çetin", "ozgecmis" => "Sosyal medya ve içerik pazarlaması ile markanın görünürlüğünü artırıyor."],
      ["isim" => "Ahmet Yıldız", "ozgecmis" => "Veri analizi ile müşteri davranışlarını anlamlandırıyor ve satışları artırıyor."],
      ["isim" => "Selin Demirtaş", "ozgecmis" => "CRM sistemleri ve müşteri memnuniyeti süreçlerinden sorumlu."]
    ];

    foreach ($ekip as $uye) {
      echo '<div class="team-card">';
      echo '<h5>' . $uye['isim'] . '</h5>';
      echo '<p>' . $uye['ozgecmis'] . '</p>';
      echo '</div>';
    }
  ?>

  <div class="text-center mt-4">
    <a href="index.php" class="btn btn-secondary">Ana Sayfaya Dön</a>
  </div>
</div>

</body>
</html>
