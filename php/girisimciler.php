<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Girişimciler</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f0f2f5;
      font-family: 'Montserrat', sans-serif;
      margin: 0; /* Canvas için tam ekran için */
      overflow-x: hidden;
    }
    .card {
      border-radius: 15px;
      transition: transform 0.2s;
    }
    .card:hover {
      transform: scale(1.02);
    }
    .card img {
      height: 250px;
      object-fit: cover;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
    }
    /* Fireworks canvas ayarları */
    canvas.fireworks {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      pointer-events: none; /* Üzerine tıklanabilirliği engeller */
      z-index: 0; /* İçeriğin arkasında kalacak */
    }
    /* Container'ın ön planda görünmesi için z-index artırıldı */
    .container {
      position: relative;
      z-index: 1;
    }
  </style>
</head>
<body>

<div class="container py-5">
  <h2 class="text-center text-primary mb-5">GİRİŞİMCİLERİMİZ: YÜZLERCE EMEKÇİ ETİCARET'TE!</h2>

  <div class="row row-cols-1 row-cols-md-4 g-4">
    <!-- Girişimci kartları buraya aynı şekilde -->
    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://image.hurimg.com/i/hurriyet/75/0x0/646fd2494e3fe00ddc5c55a0.jpg" class="card-img-top" alt="Ahmet Kaya">
        <div class="card-body">
          <h5 class="card-title">Ahmet Tanrısever</h5>
          <p class="card-text">Yapay zeka temelli pazarlama çözümleri geliştiriyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://www.batiekspres.com/static/2024/05/18/deniz-yosunundan-gida-ve-cevre-dostu-ambalaj-uretildi-1716032043-138_small.jpg" class="card-img-top" alt="Elif Demir">
        <div class="card-body">
          <h5 class="card-title">Elif Demir</h5>
          <p class="card-text">E-ticaret alanında sürdürülebilir ürünler satıyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="data:image/jpeg;base64,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" class="card-img-top" alt="Mert Özkan">
        <div class="card-body">
          <h5 class="card-title">Mert Özkan</h5>
          <p class="card-text">Dedesinden öğrendiği ahşap işlemeciliğini, sanata dönüştürüp ülkenin dört bir yanına yayıyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://png.pngtree.com/thumb_back/fw800/background/20220503/pngtree-jewelry-designer-with-selection-of-pieces-measuring-tape-fabric-cloth-photo-image_36302120.jpg" class="card-img-top" alt="Zeynep Yılmaz">
        <div class="card-body">
          <h5 class="card-title">Zeynep Yılmaz</h5>
          <p class="card-text">Mobil uygulama geliştirme alanında uzman yazılımcı olan Zeynep, yaptığı takıları sizlerle buluşturuyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://media.istockphoto.com/id/697675522/tr/foto%C4%9Fraf/%C3%A7anak-%C3%A7%C3%B6mlek-at%C3%B6lye-seramik-sanat-kavram%C4%B1-yong-erkek-heykeltra%C5%9Fl%C4%B1k-eller-arac%C4%B1-parmak-ve-su.jpg?s=612x612&w=0&k=20&c=TA-ebIgJPxN81cdMUgg42YG8YHHGylOOxroq2WKMxew=" class="card-img-top" alt="Emre Karaca">
        <div class="card-body">
          <h5 class="card-title">Emre Karaca</h5>
          <p class="card-text">Fintech çözümleri ile bankacılık sistemlerini dönüştüren Emre, artık bir seramik ustası.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://binyaprak.com/images/mkm/supporters/dilsah_tek_1x1.png" class="card-img-top" alt="Sena Uçar">
        <div class="card-body">
          <h5 class="card-title">Sena Uçar</h5>
          <p class="card-text">Kadın girişimciler için mentorluk ve destek programları yürütüyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://cdn-blog.superprof.com/blog_tr/wp-content/uploads/2021/12/online-ozel-ders-ogretmenligi-scaled.jpg" class="card-img-top" alt="Burak Soylu">
        <div class="card-body">
          <h5 class="card-title">Burak Soylu</h5>
          <p class="card-text">Ege'de bir kasabaya yerleşen Burak, öğrencilerine online dokuma eğitimi veriyor.</p>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card h-100 shadow">
        <img src="https://st.depositphotos.com/1049680/61671/i/450/depositphotos_616712424-stock-photo-middle-age-blonde-woman-working.jpg" class="card-img-top" alt="Nisa Kurt">
        <div class="card-body">
          <h5 class="card-title">Nisa Kurt</h5>
          <p class="card-text">Çevre dostu ambalaj üretimi yapan bir sosyal girişimci.</p>
        </div>
      </div>
    </div>

  </div>

  <div class="text-center mt-4">
    <a href="seller_register.php" class="btn btn-secondary">Sen De Bize Katıl!</a>
</div>

</div>

<canvas class="fireworks"></canvas>

<script>
  // Fireworks effect kodları

  const canvas = document.querySelector('.fireworks');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  function random(min, max) {
      return Math.random() * (max - min) + min;
  }

  class Firework {
      constructor() {
          this.x = Math.random() * canvas.width;
          this.y = canvas.height;
          this.targetY = random(100, canvas.height / 2);
          this.speed = random(2, 5);
          this.radius = random(2, 4);
          this.color = `hsl(${random(0, 360)}, 100%, 50%)`;
          this.exploded = false;
          this.particles = [];
      }

      update() {
          if (this.y > this.targetY && !this.exploded) {
              this.y -= this.speed;
          } else {
              if (!this.exploded) {
                this.exploded = true;
                for (let i = 0; i < 100; i++) {
                    this.particles.push(new Particle(this.x, this.y, this.color));
                }
              }
          }
          this.particles.forEach(p => p.update());
          // Partikülleri güncelle
      }

      draw() {
          if (!this.exploded) {
              ctx.beginPath();
              ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
              ctx.fillStyle = this.color;
              ctx.fill();
          } else {
              this.particles.forEach(particle => particle.draw());
          }
      }
  }

  class Particle {
      constructor(x, y, color) {
          this.x = x;
          this.y = y;
          this.speed = random(1, 5);
          this.angle = random(0, Math.PI * 2);
          this.radius = random(1, 3);
          this.color = color;
          this.alpha = 1;
          this.decay = random(0.01, 0.03);
      }

      update() {
          this.x += Math.cos(this.angle) * this.speed;
          this.y += Math.sin(this.angle) * this.speed;
          this.alpha -= this.decay;
      }

      draw() {
          ctx.save();
          ctx.globalAlpha = this.alpha;
          ctx.beginPath();
          ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
          ctx.fillStyle = this.color;
          ctx.fill();
          ctx.restore();
      }
  }

  let fireworks = [];

  function animate() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (Math.random() < 0.01) { // Hava fişek çıkma sıklığı, artırıp azaltabilirsin
          fireworks.push(new Firework());
      }
      fireworks.forEach((firework, index) => {
          firework.update();
          firework.draw();
          if (firework.exploded && firework.particles.every(p => p.alpha <= 0)) {
              fireworks.splice(index, 1);
          }
      });
      requestAnimationFrame(animate);
  }

  animate();

  window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  });
</script>

</body>
</html>
