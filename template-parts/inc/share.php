<?php 
if ( is_404() ) : 
	$title_share = get_bloginfo('name');
	$url_share = get_bloginfo('url'); 
else :
	$title_share = get_the_title();
	$url_share = get_the_permalink();
endif;
?>              
<a title="Compartilhe no Facebook" rel="noopener noreferrer" onclick='javacript: window.open("http://www.facebook.com/sharer/sharer.php?u=<?php echo $url_share; ?>&title=<?php echo $title_share; ?>&display=popup",null,"height=200,width=600,status=yes,toolbar=no,menubar=no,location=no"); return false;' href="" class="facebook fab fa-facebook"><span class="d-none">Facebook</span></a>
<a title="Compartilhe no Twitter" rel="noopener noreferrer" onclick='javacript: window.open("https://twitter.com/share?url=<?php echo $url_share; ?>&text=<?php echo $title_share; ?>",null,"height=470,width=600,status=yes,toolbar=no,menubar=no,location=no"); return false;' href="" class="fab fa-twitter"><span class="d-none">Twitter</span></a>
<?php if ( wp_is_mobile() ) : $url_whats = 'api'; else : $url_whats = 'web'; endif; ?>
<a title="Compartilhe no Whatsapp" rel="noopener noreferrer" href="https://<?php echo $url_whats; ?>.whatsapp.com/send?text=<?php echo "Olá, vi o anúncio '".strtoupper(get_the_title())."' no site Bazar Bikes e estou compartilhando com você! ".get_the_permalink(); ?>" class="fab fa-whatsapp" target="_blank" title="Compartilhar no Whatsapp" data-action="share/whatsapp/share"><span class="d-none">Whatsapp</span></a>