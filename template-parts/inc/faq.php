<?php
global $schema;
$faq_items = array(
    array(
        'pergunta' => 'O que é o Bazar Bikes?',
        'resposta' => 'Uma plataforma digital criada para ajudar os ciclistas a terem uma ferramenta completa e segura para anunciar seus produtos, novos e usados. Somos um classificado grátis, especializado em bicicletas.'
    ),
    array(
        'pergunta' => 'Por que anunciar na Bazar Bikes?',
        'resposta' => 'Com uma diversidade de bicicletas e peças, o Bazar Bikes oferece a você a chance de promover seus produtos para um público altamente segmentado interessado no universo das bicicletas.'
    ),
    array(
        'pergunta' => 'Quem pode anunciar no Bazar Bikes?',
        'resposta' => 'Nosso foco são ciclistas. Qualquer pessoa que possua um CPF válido no território Brasileiro, e que deseje comprar ou vender uma bicicleta, XXXXXX é o portal ideal.'
    ),
    array(
        'pergunta' => 'Preciso pagar para utilizar este serviço?',
        'resposta' => 'O Bazar Bikes é 100% grátis para quem quer comprar e para quem quer vender. Não cobramos nenhum tipo de assinatura ou taxa para que os usuários acessem nossa ferramenta.'
    ),
    array(
        'pergunta' => 'Quanto custa anunciar na Bazar Bikes?',
        'resposta' => 'O Bazar Bikes é 100% grátis. Não cobramos taxas nem comissóes, você cria seu cadastro e anuncia seu produto, se ele estiver dentro das nossa <a href="' . home_url('/termos-de-uso/') . '" title="Política de Uso">Política de Uso</a> é publicado e passar a ser exibido em nossa busca, imeditamente e gratuitamente.'
    ),
    array(
        'pergunta' => 'O Bazar Bikes é uma loja online?',
        'resposta' => 'Somos um classificado de bicicletas grátis, todos os anúncios são de responsabilidade de seus criadores, não detemos posse, nem intermediamos negociações. Nosso objetivo é fornecer tecnologia para facilitar a vida dos ciclistas.'
    )    
);
// Gerar schema FAQPage se o objeto schema estiver disponível
if (isset($schema) && is_object($schema) && method_exists($schema, 'schema_FAQPage')) {
    $schema->schema_FAQPage($faq_items);
}
$grid = isset($args ) && !empty($args['grid']) ? $args['grid'] : '1';
?>
<ul id="faq-toogle" class="faq grid-<?php echo $grid; ?>">
    <?php foreach ($faq_items as $item) : ?>
    <li class="faq-item">
        <h3 class="bold toogleLink">
            <?php echo $item['pergunta']; ?>
            <i class="fas fa-chevron-down"></i>
        </h3>
        <div class="question box">
            <p><?php echo $item['resposta']; ?></p>
        </div>
    </li><!-- /faq-item -->
    <?php endforeach; ?>
</ul>