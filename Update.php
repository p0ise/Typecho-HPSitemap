<?php
if (!function_exists('log_to_client')) {
    function log_to_client($msg)
    {
        echo $msg."<br/>\n";
        //        file_put_contents('/tmp/update_log.log',$msg."\n",FILE_APPEND);
        ob_flush();
        flush();
    }
}
class HPSitemap_Update extends Widget_Abstract_Contents implements Widget_Interface_Do
{
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        $this->sitemap_file_index=1;
        $this->tmp_list=array();
        $this->array_sitemaps=array();
    }
    public function die_with_json($code, $msg)
    {
        $array = array(
                    'ret'=>$code,
                    'msg'=>$msg
                );
        die(json_encode($array));
    }
    /**
     * 绑定动作
     *
     * @access public
     * @return void
     */
    public function action()
    {
        @ set_time_limit(60);
        //时间限制
        define('SITE_URL', Helper::options()->siteUrl);
        define('MAX_LEN_PER_PROCESS', 1000);
        $request = Typecho_Request::getInstance();
        //SITEMAP_FULL_DIR
        //__TYPECHO_ROOT_DIR__
        @$settings = Helper::options()->plugin('HPSitemap');
        if (!$settings) {
            $this->die_with_json(1001, '未开启Typecho插件');
        }
        $auth_key = $settings->sitemap_user_auth;
        $key = $request->get('auth', 'yourkey');
        if (empty($auth_key) || empty($key) || $auth_key !== $key) {
            $this->die_with_json(false, 'Invalid auth key');
        }
        $sitemap_dir = $settings->sitemap_dir;
        if (empty($sitemap_dir)) {
            $this->die_with_json(1002, '未设置sitemap目录');
        }
        //检查目录环境
        $sitemap_dir = trim($sitemap_dir, '/')==''?'':trim($sitemap_dir, '/').'/';
        define('SITEMAP_DIR', $sitemap_dir);
        define('SITEMAP_FULL_DIR', __TYPECHO_ROOT_DIR__.'/'.SITEMAP_DIR);
        if (!is_dir(SITEMAP_FULL_DIR)) {
            if (!mkdir(SITEMAP_FULL_DIR)) {
                $this->die_with_json(1003, '创建sitemap路径失败：'.SITEMAP_FULL_DIR);
            }
        }
        if (!function_exists('array_map')) {
            $this->die_with_json(1004, '函数array_map不存在');
        }
        $db = Typecho_Db::get();
        $post_query = $db->select('cid,slug, modified as last_modified')
                    ->from('table.contents')
                    ->where('status = ?', 'publish')
                    ->where('type =?', 'post')
                    ->where('created <= ?', time());
        //筛除预发布
        $page_query = $db->select('cid,slug, modified as last_modified')
                    ->from('table.contents')
                    ->where('status = ?', 'publish')
                    ->where('type =?', 'page')
                    ->where('created <= ?', time());
        $category_query = $db->select('mid, slug, parent, unix_timestamp() as last_modified')
                    ->from('table.metas')
                    ->where('type =?', 'category');
        //生成category的sitemap
        $list = $this->change_sql_list_to_sitemap_format('category', $db->fetchAll($category_query));
        $this->proxy_build_sitemap($list);
        log_to_client("Done for categories index.");
        //生成page的sitemap
        $list = $this->change_sql_list_to_sitemap_format('page', $db->fetchAll($page_query));
        $this->proxy_build_sitemap($list);
        log_to_client("Done for pages index.");
        //生成post的sitemap
        $list = $this->change_sql_list_to_sitemap_format('post', $db->fetchAll($post_query));
        $this->proxy_build_sitemap($list);
        log_to_client("Done for posts index.");
        //生成剩余sitemap
        $this->build_rest();
        log_to_client("Done for remaining index.");
        //生成最后的sitemap_index文件
        $sitemap_index_filename = SITEMAP_FULL_DIR.'sitemap.xml';
        $ret = file_put_contents($sitemap_index_filename, $this->build_site_map_index());
        if (!$ret) {
            $this->die_with_json(1006, 'Error on generate sitemap index file: '.$sitemap_index_filename);
        }
        log_to_client("Done for sitemap generating, result sitemap file is ". $sitemap_index_filename);
    }
    protected function build_post_url($post)
    {
        $options = Helper::options();
        $db = Typecho_Db::get();
        $routeExists = (null != Typecho_Router::get('post'));
        if (!is_null($routeExists)) {
            $post['categories'] = $db->fetchAll($db->select()->from('table.metas')
                                ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                                ->where('table.relationships.cid = ?', $post['cid'])
                                ->where('table.metas.type = ?', 'category')
                                ->order('table.metas.order', Typecho_Db::SORT_ASC));
            $post['category'] = urlencode(current(Typecho_Common::arrayFlatten($post['categories'], 'slug')));
            //多级分类
            $post['directory']=$post['category'];
            foreach ($post['categories'] as $category) {
                if (0!=$category['parent']) {
                    $parent = $db->fetchRow($db->select()->from('table.metas')
                                        ->where('table.metas.mid = ?', $category['parent']));
                    $post['directory'] = urlencode($parent['slug']).'/'.urlencode($category['slug']);
                    break;
                }
            }
            $post['slug'] = urlencode($post['slug']);
            $post['date'] = new Typecho_Date($post['created']);
            $post['year'] = $post['date']->year;
            $post['month'] = $post['date']->month;
            $post['day'] = $post['date']->day;
        }
        $post['pathinfo'] = $routeExists ? Typecho_Router::url('post', $post) : '#';
        $url = Typecho_Common::url($post['pathinfo'], $options->index);
        return $url;
    }
    protected function build_page_url($page)
    {
        $url = Typecho_Router::url('page', $page);
        return Helper::options()->index.$url;
    }
    protected function build_category_url($cat)
    {
        if (0!=$cat['parent']) {
            $parent = $db->fetchRow($db->select()->from('table.metas')
                         ->where('table.metas.mid = ?', $cat['parent']));
            $cat['directory'] = urlencode($parent['slug']).'/'.urlencode($cat['slug']);
        }
        $url =  Typecho_Router::url('category', $cat);
        return Helper::options()->index.$url;
    }
    protected function build_site_map_xml_content($list)
    {
        @$mobile = Helper::options()->plugin('HPSitemap')->mobile;
        $str='<?xml version="1.0" encoding="UTF-8"?>'."\n";
        if ($mobile) {
            $str .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '."\n".'xmlns:mobile="http://www.baidu.com/schemas/sitemap-mobile/1/">'."\n";
        } else {
            $str .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        }
        foreach ($list as $item) {
            $str .= '<url>'."\n";
            $str .= "<loc>{$item['loc']}</loc>\n";
            if ($mobile) {
                $str .= '<mobile:mobile type="pc,mobile"/>';
            }
            $str .= "<lastmod>{$item['lastmod']}</lastmod>\n";
            $str .= '<changefreq>daily</changefreq>'."\n";
            $str .= '<priority>0.8</priority>'."\n";
            $str .= '</url>'."\n";
        }
        $str .= '</urlset>';
        return $str;
    }
    protected function build_site_map_index()
    {
        $str='<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $str .='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($this->array_sitemaps as $item) {
            $str .='<sitemap>'."\n";
            $str.="<loc>{$item['loc']}</loc>"."\n";
            $str.="<lastmod>{$item['lastmod']}</lastmod>"."\n";
            $str .='</sitemap>'."\n";
        }
        $str .='</sitemapindex>';
        return $str;
    }
    protected function change_sql_list_to_sitemap_format($type, $list)
    {
        if ('post'==$type) {
            $result = array_map(function ($item) {
                return array(
                                'loc'=>$this->build_post_url($item),
                                'lastmod'=>gmdate("Y-m-d\TH:i:s+08:00", $item['last_modified'])
                            );
            }, $list);
        } elseif ('page'==$type) {
            $result = array_map(function ($item) {
                return array(
                                'loc'=>$this->build_page_url($item),
                                'lastmod'=>gmdate("Y-m-d\TH:i:s+08:00", $item['last_modified'])
                            );
            }, $list);
        } elseif ('category'==$type) {
            $result = array_map(function ($item) {
                return array(
                                'loc'=>$this->build_category_url($item),
                                'lastmod'=>gmdate("Y-m-d\TH:i:s+08:00", $item['last_modified'])
                            );
            }, $list);
        } else {
            $this->die_with_json(1005, '函数参数错误');
        }
        return $result;
    }
    protected function build_sitemap_file_for_sql_items($list, $file_index)
    {
        $str_xml = $this->build_site_map_xml_content($list);
        $sitemap_filename = 'sitemap_'.$file_index.'.xml';
        $sitemap_full_filename = SITEMAP_FULL_DIR.$sitemap_filename;
        $ret = file_put_contents($sitemap_full_filename, $str_xml);
        if (!$ret) {
            die('Error on generate sitemap file: '.$sitemap_full_filename);
        } else {
            $tmp = array(
                            'loc'=>SITE_URL.SITEMAP_DIR.$sitemap_filename,
                            'lastmod'=>gmdate('Y-m-d\TH:i:s+08:00', time())
                        );
            return $tmp;
        }
    }
    protected function proxy_build_sitemap($list)
    {
        if (empty($list)) {
            return;
        }
        $list=array_merge($this->tmp_list, $list);
        $this->tmp_list=array();
        $times=intval(count($list)/MAX_LEN_PER_PROCESS);
        $list=array_chunk($list, MAX_LEN_PER_PROCESS);
        for ($i=0;$i<$times;$i++) {
            log_to_client('Generating sitemap index for file '.$this->sitemap_file_index);
            $sitemap_info=$this->build_sitemap_file_for_sql_items($list[$i], $this->sitemap_file_index);
            $array_sitemaps=$this->array_sitemaps;
            array_push($array_sitemaps, $sitemap_info);
            $this->array_sitemaps=$array_sitemaps;
            $this->sitemap_file_index++;
        }
        if (array_key_exists($times, $list)) {
            $this->tmp_list=$list[$times];
        }
    }
    protected function build_rest()
    {
        if (empty($this->tmp_list)) {
            return;
        }
        $list = $this->tmp_list;
        $this->tmp_list = array();
        log_to_client('Generating sitemap index for file '.$this->sitemap_file_index);
        $sitemap_info=$this->build_sitemap_file_for_sql_items($list, $this->sitemap_file_index);
        $array_sitemaps=$this->array_sitemaps;
        array_push($array_sitemaps, $sitemap_info);
        $this->array_sitemaps=$array_sitemaps;
        $this->sitemap_file_index++;
    }
}
