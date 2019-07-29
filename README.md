# Typecho-HPSitemap
快速生成typecho站点地图，采用百度移动sitemap协议，对百度爬虫更友好。



<<<<<<< HEAD
- [x] 自定义链接解析
=======
- [-] 自定义链接解析
>>>>>>> d42b61d0b5d3af07690289342541fbf70c63c99c



## 实现原理：

通过了Sitemap路径索引所方式，以1000篇文章为一个节点，生成一个单独的Sitemap.xml文件，然后再对生成的Sitemap.xml链接进行一个聚合。



## 食用方法：
![Typecho 站点地图生成插件 Sitemap 大数据版优化.png](https://blog.irow.top/usr/uploads/2019/07/2806146079.png)
1. 下载后解压并将文件夹重命名为HPSitemap
2. 后台设置生成目录和密匙
3. 访问生成sitemap方法的url，测试生成sitemap，并前往sitemap路径检验是否成功
  - URL构造为`你的域名/action/update_sitemap?auth=密匙`
  - 如果开启了伪静态，则URL为`你的域名/index.php/action/update_sitemap?auth=密匙`

4. 监控该URL（频率可设置为5分钟左右）



## 插件地址：

优化版地址：
- github主页：<https://github.com/invelop/Typecho-HPSitemap>
- 码云主页：<https://gitee.com/ETAS/Typecho-HPSitemap>

原插件地址：https://www.typecho.wiki/archives/typecho-sitemap-generation-plugin-sitemap-bigdata.html
