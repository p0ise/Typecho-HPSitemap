<?php

/**
 * sitemap生成工具。<a href='www.typecho.wiki/archives/typecho-sitemap-generation-plugin-sitemap-bigdata.html'>需要帮助?</a>
 *
 * @category widget
 * @package HPSitemap
 * @author Roogle&雷鬼&承影
 * @version 2.1
 * @link https://www.typecho.wiki
 */

class HPSitemap_Plugin implements Typecho_Plugin_Interface
{
    public static function activate(){
        Helper::addAction('update_sitemap', 'HPSitemap_Update');

    }

    public static function deactivate(){
	    Helper::removeAction('update_sitemap');

    }

    public static function config(Typecho_Widget_Helper_Form $form){

        $sitemap_dir = new Typecho_Widget_Helper_Form_Element_Text(
            'sitemap_dir',NULL ,'sitemap',
            _t('设置生成sitemap的目录,根目录是usr目录。'),
            _t('譬如设置sitemap,则会在usr/sitemap/下生成sitemap.xml,请保证此目录可访问')
        );
        $form->addInput($sitemap_dir);

        $import_user_auth = new Typecho_Widget_Helper_Form_Element_Text(
            'sitemap_user_auth',NULL ,'',
            _t('设置调用接口时的密匙'),
            _t('接口地址：你的网站/action/update_sitemap?auth=接口密匙')
        );
        $form->addInput($import_user_auth);

    }


    public static function personalConfig(Typecho_Widget_Helper_Form $form){

    }


}