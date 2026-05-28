<?php

class PluginRadiosMenu extends CommonGLPI {
    
    static $rightname = 'plugin_radios';
    
    static function getMenuName() {
        return __('Radios', 'radios');
    }
    
    static function getMenuContent() {
        $menu = [];
        
        $menu['title'] = self::getMenuName();
        $menu['page'] = '/plugins/radios/front/radio.php';
        $menu['icon'] = 'fa-solid fa-walkie-talkie';
        
        return $menu;
    }
    
    static function canView() {
        return true;
    }
    
    static function canCreate() {
        return true;
    }
}