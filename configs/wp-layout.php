<?php
function close_divs( $count ) {
    for( $i = 0; $i < $count; $i++ ) {
        echo '</div>';
    }
}

function page_default_layout( $class ){

    $class_attr = is_array($class) 
        ? implode(' ', $class) 
        : $class;

    $class_add = ( $class_attr )
        ? ' '.htmlspecialchars($class_attr, ENT_QUOTES, 'UTF-8')
        : '';

    echo '<div class="row align-center page'.$class_add.'">
    <div class="s-11 l-9 col">';
}
function close_page_default_layout(){
    close_divs(2);
}

function box_content(){
    echo '<div class="box-content">';
}
function box_content_forms(){
    echo '<div class="box-content box-content-forms">';
}


function home_layout(){
    echo '<div class="row align-center page">
    <div class="s-11 col">';
}

function index_layout(){
    echo '<div class="row align-center page">
    <div class="s-11 l-12 col">';
}

function after_box_content_large(){
    echo '<div class="row align-center page">
    <div class="s-11 l-9 col">';
}
function large_10_cols(){
    echo '<div class="row align-center page">
    <div class="s-10 m-11 col">';
}



function large_clear_content(){
    echo '<div class="row align-center page">
    <div class="s-12 col">';    
}
function large_content(){
    after_box_content_large();
    box_content();
}

function large_content_forms(){
    after_box_content_large();
    box_content_forms();
}

function large_content_alt(){
    home_layout();
    box_content();
}

function xlarge_content(){
    echo '<div class="row align-center page">
    <div class="s-11 l-10 col">
    <div class="box-content_">';
}

function medium_content(){
    echo '<div class="row align-center page">
    <div class="s-11 m-9 l-7 col">
    <div class="box-content small">';
}

function small_content(){
    echo '<div class="row align-center page">
    <div class="s-11 m-7 l-5 col">
    <div class="box-content small">';
}

function close_content(){
    echo '</div><!-- /box-content --></div></div>';
    
}
function close_clear_content(){
    echo '</div></div>';
    
}