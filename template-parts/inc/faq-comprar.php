<?php
global $schema;
$faq_items = array(
    array(
        'pergunta' => 'É seguro comprar no Bazar Bikes?',
        'resposta' => 'O Bazar Bikes é uma plataforma de classificados que conecta compradores e vendedores. Recomendamos sempre verificar a procedência do produto (pedir nota fiscal ou recibo), encontrar o vendedor em locais públicos e movimentados, e inspecionar o produto pessoalmente antes de finalizar o pagamento.'
    ),
    array(
        'pergunta' => 'Como funciona a negociação e o pagamento?',
        'resposta' => 'A negociação é feita diretamente entre você e o vendedor através do chat do anúncio. O Bazar Bikes não intermedia pagamentos. Combine o método de pagamento (dinheiro, PIX, transferência) e a forma de entrega/retirada diretamente com o vendedor.'
    ),
    array(
        'pergunta' => 'Posso negociar o preço do produto?',
        'resposta' => 'Sim! O Bazar Bikes é um ambiente aberto a negociações. Use o chat do anúncio para fazer uma oferta ao vendedor. Seja respeitoso e faça propostas justas baseadas no estado e no valor de mercado do produto.'
    ),
    array(
        'pergunta' => 'Como entro em contato com o vendedor?',
        'resposta' => 'Cada anúncio possui um sistema de chat integrado. Clique no botão "Falar com vendedor" ou "Enviar mensagem" no anúncio para iniciar uma conversa. Alguns vendedores também optam por exibir seu telefone diretamente no anúncio.'
    ),
    array(
        'pergunta' => 'O que devo verificar antes de comprar um produto usado?',
        'resposta' => 'Sempre verifique as fotos detalhadas, leia a descrição completa do anúncio e tire todas as dúvidas com o vendedor. Para bicicletas, verifique quadro, componentes, freios e rodas. Para peças, confirme compatibilidade e estado. Peça histórico de uso e manutenção quando possível.'
    ),
    array(
        'pergunta' => 'O Bazar Bikes oferece garantia sobre os produtos?',
        'resposta' => 'Não. Como somos um classificado, a negociação é direta entre comprador e vendedor. Não oferecemos garantia sobre produtos usados. Por isso, é essencial inspecionar o produto pessoalmente antes de comprar e tirar todas as dúvidas com o vendedor.'
    ),
    array(
        'pergunta' => 'Como funciona o frete e a entrega dos produtos?',
        'resposta' => 'O frete e a forma de entrega são combinados diretamente com o vendedor. Para bicicletas completas, a retirada em mãos é o método mais comum. Para peças e acessórios menores, é possível enviar pelos Correios ou transportadoras. Combine o valor do frete e a forma de envio antes de fechar o negócio.'
    ),
    array(
        'pergunta' => 'Quais são as formas de pagamento mais seguras?',
        'resposta' => 'Recomendamos sempre encontrar o vendedor pessoalmente para inspecionar o produto antes de pagar. Para pagamentos presenciais, prefira PIX ou transferência bancária na hora da retirada. Evite fazer pagamentos antecipados sem ver o produto, especialmente em valores altos.'
    ),
    array(
        'pergunta' => 'Como usar os filtros de busca para encontrar o que procuro?',
        'resposta' => 'Use os filtros disponíveis na página de busca para refinar sua pesquisa: categoria (Bicicleta, Peça, Acessório), modalidade, cidade, faixa de preço, conservação e outros. Quanto mais específicos os filtros, mais precisos serão os resultados. Você também pode usar palavras-chave na busca.'
    ),
    array(
        'pergunta' => 'O que fazer se o produto comprado apresentar problema?',
        'resposta' => 'Como a negociação é direta entre usuários, recomendamos sempre inspecionar o produto pessoalmente antes de finalizar o pagamento. Se houver problemas após a compra, entre em contato com o vendedor pelo chat. Em caso de divergências, documente tudo (fotos, conversas) e, se necessário, busque orientação jurídica.'
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