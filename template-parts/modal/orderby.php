<!-- mobile option -->
<div id="filter-ordeby" class="modal-nav show-for-s-only">
  <div class="bx auto">
    <div class="modal-nav-head">
      <i class="fa fa-sort-amount-down red"></i>
      <small>Ordenar por</small>
      <a id="bt-close-order-by" href="#" class="close bold bt-close-filter">Fechar</a>
    </div><!-- /form-head -->
    <div class="modal-nav-body">
      <ul>
        <li data-value="date_asc" class="<?php if (isset($_GET['order']) && $_GET['order'] == 'date_asc')
          echo 'selected'; ?>">
          Mais antigos
        </li>
        <li data-value="date_desc" class="<?php if (isset($_GET['order']) && $_GET['order'] == 'date_desc')
          echo 'selected'; ?>">
          Mais recentes
        </li>
        <li data-value="price_asc" class="<?php if (isset($_GET['order']) && $_GET['order'] == 'price_asc')
          echo 'selected'; ?>">
          Menor preço
        </li>
        <li data-value="price_desc" class="<?php if (isset($_GET['order']) && $_GET['order'] == 'price_desc')
          echo 'selected'; ?>">
          Maior preço
        </li>
      </ul>
    </div>
  </div>
  <div class="bg-overlay"></div>
</div>