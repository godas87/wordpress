<?php
/**
 * Gerenciamento de Cache para o WordPress
 */

class __Bazar_Cache {
    private static $instance = null;
    private $cache_groups = array(
        'post_tags',
        'tag_relationships',
        'related_posts',
        'taxonomy_terms'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_cache_groups();
        $this->init_scheduled_cleanup();
    }

    private function init_cache_groups() {
        foreach ($this->cache_groups as $group) {
            wp_cache_add_non_persistent_groups($group);
        }
    }

    private function init_scheduled_cleanup() {
        if (!wp_next_scheduled('bazar_cleanup_tags')) {
            wp_schedule_event(time(), 'daily', 'bazar_cleanup_tags');
        }
    }

    public function get_cache_key($key, $group = '') {
        return md5($key . $group);
    }

    public function get($key, $group = '') {
        $cache_key = $this->get_cache_key($key, $group);
        return wp_cache_get($cache_key, $group);
    }

    public function set($key, $data, $group = '', $expire = 3600) {
        $cache_key = $this->get_cache_key($key, $group);
        return wp_cache_set($cache_key, $data, $group, $expire);
    }

    public function delete($key, $group = '') {
        $cache_key = $this->get_cache_key($key, $group);
        return wp_cache_delete($cache_key, $group);
    }

    public function flush_group($group) {
        return wp_cache_flush_group($group);
    }

    public function cleanup_tags() {
        $tags = get_terms(array(
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'number' => 1000
        ));
        
        if (!is_wp_error($tags)) {
            $deleted = 0;
            foreach ($tags as $tag) {
                if ($tag->count === 0) {
                    wp_delete_term($tag->term_id, 'post_tag');
                    $deleted++;
                }
            }
            return $deleted;
        }
        return 0;
    }
    
    /**
     * Previne clonagem (Singleton)
     */
    private function __clone() {}
    
    /**
     * Previne unserialize (Singleton)
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Inicializa o cache
add_action('init', function() {
    __Bazar_Cache::get_instance();
});

// Adiciona o hook para limpeza de tags
add_action('bazar_cleanup_tags', function() {
    $cache = __Bazar_Cache::get_instance();
    $cache->cleanup_tags();
});

// Limpa o evento quando o tema for desativado
add_action('switch_theme', function() {
    wp_clear_scheduled_hook('bazar_cleanup_tags');
}); 