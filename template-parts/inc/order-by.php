<!-- desktop option -->
<div class="hide-for-s-only text-right">
  <i class="fa fa-sort-amount-down"></i>
  <small>Ordenar por:</small>
  <select name="order" id="order-select">
    <option value="">Ordenar por</option>
    <option value="date_asc" <?php if (isset($_GET['order']) && $_GET['order'] == 'date_asc')
      echo 'selected'; ?>>
      Mais antigos
    </option>
    <option value="date_desc" <?php if (isset($_GET['order']) && $_GET['order'] == 'date_desc')
      echo 'selected'; ?>>
      Mais recentes
    </option>
    <option value="price_asc" <?php if (isset($_GET['order']) && $_GET['order'] == 'price_asc')
      echo 'selected'; ?>>
      Menor preço
    </option>
    <option value="price_desc" <?php if (isset($_GET['order']) && $_GET['order'] == 'price_desc')
      echo 'selected'; ?>>
      Maior preço
    </option>
  </select>
</div>