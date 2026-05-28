<?php

class PluginTermosMenu extends CommonGLPI {
    
    static $rightname = 'plugin_termos';
    
    static function getMenuName() {
        return __('Termos', 'termos');
    }
    
    static function getMenuContent() {
        $menu = [];
        
        $menu['title'] = self::getMenuName();
        $menu['page'] = '/plugins/termos/front/menu.php'; // ← ALTERADO AQUI
        $menu['icon'] = 'fa-solid fa-pen-to-square';
        
        return $menu;
    }
    
    static function canView() {
        return true;
    }
    
    static function canCreate() {
        return true;
    }
}