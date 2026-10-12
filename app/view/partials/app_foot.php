      </main>
    </div>

    <!-- ============================ Barra de ações (mobile) ============================ -->
    <nav class="bottom-actions">
      <a class="bar-btn" href="viagens.php">
        <i class="fa-solid fa-plane-up"></i>Viagem
      </a>
      <a class="bar-btn danger" href="gastos.php">
        <i class="fa-solid fa-coins"></i>Gasto
      </a>
      <a class="bar-btn home" href="painel.php" title="Painel">
        <i class="fa-solid fa-house-chimney"></i>
        <span style="font-size:.6rem;">Início</span>
      </a>
      <a class="bar-btn primary" href="compras.php">
        <i class="fa-solid fa-cart-shopping"></i>Compra
      </a>
      <a class="bar-btn" href="loja.php" title="Loja">
        <i class="fa-solid fa-store"></i>Loja
      </a>
    </nav>
  </div>

  <!-- Libs -->
  <script src="../../public/assets/js/jquery-3.7.1/jquery.min.js"></script>
  <script src="../../public/assets/js/bootstrap-5.3.3/bootstrap.bundle.min.js"></script>
  <?= $scriptsExtra ?? '' ?>
  <!-- App -->
  <script src="../../public/assets/js/app/mascaras.js"></script>
  <script src="../../public/assets/js/app/app.js"></script>
  <?= $bodyScriptExtra ?? '' ?>
</body>
</html>
