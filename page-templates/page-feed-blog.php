<?php
/* 
Template Name: Feed Blog 
Template Post Type: app
*/
header('Content-Type: text/xml');
$item = '';
$params_blog = array(			
	'posts_per_page' => 100,
	'post_type' => 'blog'
);
$the_query = new WP_Query( $params_blog );
if ( $the_query->have_posts() ) :
	$item .='
	<rss version="2.0"
		xmlns:g="http://base.google.com/ns/1.0" 
		xmlns:c="http://base.google.com/cns/1.0" 
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"
		xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
		xmlns:slash="http://purl.org/rss/1.0/modules/slash/"		
	>
	<listings>
		<title>
			<![CDATA['.get_bloginfo('name').']]>
		</title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link>
			<![CDATA['.get_bloginfo('url').']]>
		</link>		
		<language>pt-BR</language>
		<description>
			<![CDATA['.get_bloginfo('description').']]>
		</description>';
		$posts = $the_query->posts;
		foreach( $posts as $post ) :
		$item .='
		<item>
			<title>'.the_title_rss().'</title>
			<link>'.the_permalink_rss().'</link>
			<pubDate>'. mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false).'</pubDate>
			<dc:creator>'.the_author().'</dc:creator>
			<guid isPermaLink="false">'.the_guid().'</guid>
			<description><![CDATA['.the_excerpt_rss().']]></description>
			<content:encoded><![CDATA['.the_excerpt_rss().']]></content:encoded>
			'.rss_enclosure().'
			'.do_action('rss2_item').'
		</item>';
		endforeach;
	$item .= '
	</listings>
	</rss>';	
	echo $item;
	wp_reset_postdata();
endif;
?>