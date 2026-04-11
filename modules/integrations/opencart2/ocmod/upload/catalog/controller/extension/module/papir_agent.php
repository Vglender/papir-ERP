<?php
/**
 * Papir ERP Agent — OpenCart 2.x/3.x Controller
 * Route: extension/module/papir_agent
 * PHP 5.6+ compatible.
 */
class ControllerExtensionModulePapirAgent extends Controller {
    const AGENT_VERSION = '1.0.0';
    private $dbPrefix;

    public function index() {
        $this->dbPrefix = DB_PREFIX;
        $token = $this->getToken();
        $row = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'papir_agent_token' LIMIT 1");
        $storedToken = $row->num_rows ? $row->row['value'] : '';
        $row2 = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'papir_agent_status' LIMIT 1");
        $status = $row2->num_rows ? $row2->row['value'] : '0';
        $action = isset($this->request->get['action']) ? $this->request->get['action'] : '';
        if (!$status) { $this->j(array('ok'=>false,'error'=>'Agent is disabled'), 503); return; }
        if (!$storedToken || $token !== $storedToken) { $this->j(array('ok'=>false,'error'=>'Unauthorized'), 401); return; }
        switch ($action) {
            case 'ping': $this->j(array('ok'=>true,'time'=>date('Y-m-d H:i:s'))); break;
            case 'info': $this->doInfo(); break;
            case 'stats': $this->doStats(); break;
            case 'product.create': $this->doProductCreate(); break;
            case 'product.update': $this->doProductUpdate(); break;
            case 'product.delete': $this->doProductDelete(); break;
            case 'product.seo': $this->doProductSeo(); break;
            case 'product.images': $this->doProductImages(); break;
            case 'product.attributes': $this->doProductAttributes(); break;
            case 'batch.prices': $this->doBatchPrices(); break;
            case 'batch.quantity': $this->doBatchQuantity(); break;
            case 'batch.specials': $this->doBatchSpecials(); break;
            case 'category.create': $this->doCategoryCreate(); break;
            case 'category.update': $this->doCategoryUpdate(); break;
            case 'manufacturer.save': $this->doManufacturerSave(); break;
            case 'orders.list': $this->doOrdersList(); break;
            case 'orders.get': $this->doOrderGet(); break;
            case 'cache.clear': $this->doCacheClear(); break;
            default: $this->j(array('ok'=>false,'error'=>'Unknown action: '.$action), 404);
        }
    }

    private function getToken() {
        $h='';
        if(isset($_SERVER['HTTP_AUTHORIZATION'])) $h=$_SERVER['HTTP_AUTHORIZATION'];
        elseif(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $h=$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        elseif(function_exists('apache_request_headers')){$a=apache_request_headers();if(isset($a['Authorization']))$h=$a['Authorization'];}
        $t=''; if(preg_match('/Bearer\s+(.+)$/i',$h,$m))$t=trim($m[1]);
        if(!$t&&isset($_GET['api_key']))$t=$_GET['api_key'];
        if(!$t&&isset($_GET['papir_token']))$t=$_GET['papir_token'];
        return $t;
    }
    private function body(){$r=file_get_contents('php://input');$b=json_decode($r,true);return is_array($b)?$b:array();}
    private function t($n){return $this->dbPrefix.$n;}
    private function e($v){if($v===null)return'NULL';return "'".$this->db->escape($v)."'";}
    private function fa($s){$r=$this->db->query($s);return $r->rows;}
    private function fr($s){$r=$this->db->query($s);return $r->num_rows?$r->row:null;}
    private function q($s){$this->db->query($s);return $this->db->getLastId();}
    private function j($d,$c=200){if($c!==200)http_response_code($c);$this->response->addHeader('Content-Type: application/json');$this->response->setOutput(json_encode($d));}
    private function err($m,$c=400){$this->j(array('ok'=>false,'error'=>$m),$c);}
    private function req($b,$f){if(!isset($b[$f])){$this->err('Missing: '.$f);return null;}return $b[$f];}

    // === INFO ===
    private function doInfo(){
        $v='unknown';$ix=dirname(dirname(dirname(dirname(__FILE__)))).'/index.php';
        if(file_exists($ix)){$c=file_get_contents($ix);if(preg_match("/define\s*\(\s*'VERSION'\s*,\s*'([^']+)'/",$c,$m))$v=$m[1];}
        $langs=$this->fa("SELECT language_id,name,code,status FROM ".$this->t('language')." ORDER BY sort_order");
        $mods=array();
        $s=$this->fr("SELECT * FROM ".$this->t('extension')." WHERE code='simple' OR code='simplecheckout' LIMIT 1");
        if($s){$mods['simple']=array('installed'=>true);$tt=$this->fa("SHOW TABLES LIKE '".$this->t('simple')."%'");$st=array();foreach($tt as $r){$x=array_values($r);$st[]=$x[0];}$mods['simple']['tables']=$st;}
        $mods['seo_url_table']=!empty($this->fr("SHOW TABLES LIKE '".$this->t('seo_url')."'"));
        $mods['url_alias_table']=!empty($this->fr("SHOW TABLES LIKE '".$this->t('url_alias')."'"));
        $gr=$this->fa("SELECT cg.customer_group_id,cgd.name FROM ".$this->t('customer_group')." cg LEFT JOIN ".$this->t('customer_group_description')." cgd USING(customer_group_id) WHERE cgd.language_id=1 ORDER BY cg.sort_order");
        $url=defined('HTTPS_SERVER')?HTTPS_SERVER:(defined('HTTP_SERVER')?HTTP_SERVER:'');
        $this->j(array('ok'=>true,'oc_version'=>$v,'db_prefix'=>$this->dbPrefix,'languages'=>$langs,'customer_groups'=>$gr,'modules'=>$mods,'php_version'=>phpversion(),'agent_version'=>self::AGENT_VERSION,'server_time'=>date('Y-m-d H:i:s'),'store_url'=>$url));
    }

    // === STATS ===
    private function doStats(){
        $td=date('Y-m-d');$w=date('Y-m-d',strtotime('-7 days'));$mo=date('Y-m-d',strtotime('-30 days'));
        $ot=$this->fr("SELECT COUNT(*) as cnt,COALESCE(SUM(total),0) as total FROM ".$this->t('order')." WHERE DATE(date_added)='{$td}' AND order_status_id>0");
        $ow=$this->fr("SELECT COUNT(*) as cnt,COALESCE(SUM(total),0) as total FROM ".$this->t('order')." WHERE DATE(date_added)>='{$w}' AND order_status_id>0");
        $om=$this->fr("SELECT COUNT(*) as cnt,COALESCE(SUM(total),0) as total FROM ".$this->t('order')." WHERE DATE(date_added)>='{$mo}' AND order_status_id>0");
        $p=$this->fr("SELECT COUNT(*) as total,SUM(status=1) as active,SUM(quantity>0) as in_stock FROM ".$this->t('product'));
        $rc=$this->fa("SELECT o.order_id,CONCAT(o.firstname,' ',o.lastname) as customer,o.total,o.order_status_id,o.date_added,os.name as status_name FROM ".$this->t('order')." o LEFT JOIN ".$this->t('order_status')." os ON os.order_status_id=o.order_status_id AND os.language_id=1 WHERE o.order_status_id>0 ORDER BY o.order_id DESC LIMIT 10");
        $this->j(array('ok'=>true,'orders'=>array('today'=>$ot,'week'=>$ow,'month'=>$om),'products'=>$p,'recent_orders'=>$rc));
    }

    // === PRODUCT CREATE ===
    private function doProductCreate(){
        $b=$this->body();$p=$this->req($b,'product');if(!$p)return;
        $f=array('model'=>isset($p['model'])?$p['model']:'','sku'=>isset($p['sku'])?$p['sku']:'','upc'=>isset($p['upc'])?$p['upc']:'','ean'=>isset($p['ean'])?$p['ean']:'','jan'=>isset($p['jan'])?$p['jan']:'','isbn'=>isset($p['isbn'])?$p['isbn']:'','mpn'=>isset($p['mpn'])?$p['mpn']:'','location'=>isset($p['location'])?$p['location']:'','quantity'=>isset($p['quantity'])?intval($p['quantity']):0,'stock_status_id'=>isset($p['stock_status_id'])?intval($p['stock_status_id']):5,'manufacturer_id'=>isset($p['manufacturer_id'])?intval($p['manufacturer_id']):0,'price'=>isset($p['price'])?floatval($p['price']):0,'shipping'=>isset($p['shipping'])?intval($p['shipping']):1,'points'=>isset($p['points'])?intval($p['points']):0,'tax_class_id'=>isset($p['tax_class_id'])?intval($p['tax_class_id']):0,'weight'=>isset($p['weight'])?floatval($p['weight']):0,'weight_class_id'=>isset($p['weight_class_id'])?intval($p['weight_class_id']):1,'length'=>isset($p['length'])?floatval($p['length']):0,'width'=>isset($p['width'])?floatval($p['width']):0,'height'=>isset($p['height'])?floatval($p['height']):0,'length_class_id'=>isset($p['length_class_id'])?intval($p['length_class_id']):1,'subtract'=>isset($p['subtract'])?intval($p['subtract']):1,'minimum'=>isset($p['minimum'])?intval($p['minimum']):1,'sort_order'=>isset($p['sort_order'])?intval($p['sort_order']):0,'status'=>isset($p['status'])?intval($p['status']):1,'image'=>isset($p['image'])?$p['image']:'','date_available'=>isset($p['date_available'])?$p['date_available']:date('Y-m-d'),'date_added'=>date('Y-m-d H:i:s'),'date_modified'=>date('Y-m-d H:i:s'));
        foreach(array('uuid','noindex','unit','cogs','min_price','options_buy','viewed') as $x){if(isset($p[$x]))$f[$x]=$p[$x];}
        $c=array();$v=array();foreach($f as $k=>$val){$c[]='`'.$k.'`';$v[]=(is_int($val)||is_float($val))?$val:$this->e($val);}
        $this->db->query("INSERT INTO ".$this->t('product')." (".implode(',',$c).") VALUES (".implode(',',$v).")");
        $pid=$this->db->getLastId();
        $sid=isset($p['store_id'])?intval($p['store_id']):0;
        $this->q("INSERT INTO ".$this->t('product_to_store')." (product_id,store_id) VALUES ({$pid},{$sid})");
        if(isset($b['categories'])&&is_array($b['categories'])){foreach($b['categories'] as $cat){$ci=intval($cat['category_id']);$mc=isset($cat['main_category'])?intval($cat['main_category']):0;$this->q("INSERT INTO ".$this->t('product_to_category')." (product_id,category_id,main_category) VALUES ({$pid},{$ci},{$mc})");}}
        if(isset($b['descriptions']))$this->inDesc($pid,$b['descriptions']);
        if(isset($b['images']))$this->inImg($pid,$b['images']);
        if(isset($b['seo_urls']))$this->seoSave('product_id',$pid,$b['seo_urls']);
        $this->j(array('ok'=>true,'product_id'=>$pid));
    }

    // === PRODUCT UPDATE ===
    private function doProductUpdate(){
        $b=$this->body();$pid=intval($this->req($b,'product_id'));if(!$pid)return;
        if(!$this->fr("SELECT product_id FROM ".$this->t('product')." WHERE product_id={$pid}")){$this->err('Not found',404);return;}
        $u=array();
        if(isset($b['fields'])&&is_array($b['fields'])){$s=array();foreach($b['fields'] as $k=>$v){if($k==='product_id')continue;if(is_null($v))$s[]="`{$k}`=NULL";elseif(is_int($v)||is_float($v))$s[]="`{$k}`={$v}";else $s[]="`{$k}`=".$this->e($v);}if(!empty($s)){$s[]="`date_modified`=NOW()";$this->q("UPDATE ".$this->t('product')." SET ".implode(',',$s)." WHERE product_id={$pid}");$u[]='product';}}
        if(isset($b['descriptions'])){foreach($b['descriptions'] as $d){$li=intval($d['language_id']);$s=array();foreach(array('name','description','short_description','description_mini','tag','meta_title','meta_description','meta_keyword','meta_h1','image_description') as $c){if(isset($d[$c]))$s[]="`{$c}`=".$this->e($d[$c]);}if(!empty($s))$this->q("UPDATE ".$this->t('product_description')." SET ".implode(',',$s)." WHERE product_id={$pid} AND language_id={$li}");}$u[]='descriptions';}
        if(isset($b['categories'])){$this->q("DELETE FROM ".$this->t('product_to_category')." WHERE product_id={$pid}");foreach($b['categories'] as $cat){$ci=intval($cat['category_id']);$mc=isset($cat['main_category'])?intval($cat['main_category']):0;$this->q("INSERT INTO ".$this->t('product_to_category')." (product_id,category_id,main_category) VALUES ({$pid},{$ci},{$mc})");}$u[]='categories';}
        if(isset($b['seo_urls'])){$this->seoDel('product_id',$pid);$this->seoSave('product_id',$pid,$b['seo_urls']);$u[]='seo_urls';}
        $this->j(array('ok'=>true,'product_id'=>$pid,'updated'=>$u));
    }

    // === PRODUCT DELETE ===
    private function doProductDelete(){
        $b=$this->body();$pid=intval($this->req($b,'product_id'));if(!$pid)return;
        $ex=$this->fr("SELECT product_id,image FROM ".$this->t('product')." WHERE product_id={$pid}");if(!$ex){$this->err('Not found',404);return;}
        $imgs=$this->fa("SELECT image FROM ".$this->t('product_image')." WHERE product_id={$pid}");$ip=array();if(!empty($ex['image']))$ip[]=$ex['image'];foreach($imgs as $i){if(!empty($i['image']))$ip[]=$i['image'];}
        foreach(array('product_image','product_description','product_discount','product_special','product_to_category','product_to_store','product_to_layout','product_attribute','product_option','product_option_value') as $t)$this->q("DELETE FROM ".$this->t($t)." WHERE product_id={$pid}");
        $this->q("DELETE FROM ".$this->t('product_related')." WHERE product_id={$pid} OR related_id={$pid}");
        $this->seoDel('product_id',$pid);$this->q("DELETE FROM ".$this->t('product')." WHERE product_id={$pid}");
        $this->j(array('ok'=>true,'product_id'=>$pid,'images_to_delete'=>$ip));
    }

    // === PRODUCT SEO ===
    private function doProductSeo(){
        $b=$this->body();$pid=intval($this->req($b,'product_id'));if(!$pid)return;
        if(isset($b['descriptions'])){foreach($b['descriptions'] as $d){$li=intval($d['language_id']);$s=array();foreach(array('name','description','short_description','meta_title','meta_description','meta_keyword','meta_h1') as $c){if(isset($d[$c]))$s[]="`{$c}`=".$this->e($d[$c]);}if(!empty($s))$this->q("UPDATE ".$this->t('product_description')." SET ".implode(',',$s)." WHERE product_id={$pid} AND language_id={$li}");}}
        if(!empty($b['seo_urls'])){$this->seoDel('product_id',$pid);$this->seoSave('product_id',$pid,$b['seo_urls']);}
        $this->j(array('ok'=>true,'product_id'=>$pid));
    }

    // === PRODUCT IMAGES ===
    private function doProductImages(){
        $b=$this->body();$pid=intval($this->req($b,'product_id'));if(!$pid)return;
        if(isset($b['main_image']))$this->q("UPDATE ".$this->t('product')." SET image=".$this->e($b['main_image']).",date_modified=NOW() WHERE product_id={$pid}");
        if(isset($b['images'])){$old=$this->fa("SELECT image FROM ".$this->t('product_image')." WHERE product_id={$pid}");$this->q("DELETE FROM ".$this->t('product_image')." WHERE product_id={$pid}");$this->inImg($pid,$b['images']);$this->j(array('ok'=>true,'product_id'=>$pid,'old_images'=>$old));return;}
        $this->j(array('ok'=>true,'product_id'=>$pid));
    }

    // === PRODUCT ATTRIBUTES ===
    private function doProductAttributes(){
        $b=$this->body();$pid=intval($this->req($b,'product_id'));if(!$pid)return;
        $at=isset($b['attributes'])?$b['attributes']:array();
        if(isset($b['replace_all'])&&$b['replace_all'])$this->q("DELETE FROM ".$this->t('product_attribute')." WHERE product_id={$pid}");
        $n=0;foreach($at as $a){$ai=intval($a['attribute_id']);$li=intval($a['language_id']);$tx=$this->e($a['text']);
        $ex=$this->fr("SELECT product_id FROM ".$this->t('product_attribute')." WHERE product_id={$pid} AND attribute_id={$ai} AND language_id={$li}");
        if($ex)$this->q("UPDATE ".$this->t('product_attribute')." SET text={$tx} WHERE product_id={$pid} AND attribute_id={$ai} AND language_id={$li}");
        else $this->q("INSERT INTO ".$this->t('product_attribute')." (product_id,attribute_id,language_id,text) VALUES ({$pid},{$ai},{$li},{$tx})");$n++;}
        $this->j(array('ok'=>true,'product_id'=>$pid,'attributes_synced'=>$n));
    }

    // === BATCH PRICES ===
    private function doBatchPrices(){
        $b=$this->body();$items=isset($b['items'])?$b['items']:array();$n=0;
        foreach($items as $it){$pid=intval($it['product_id']);if(!$pid)continue;$s=array();
        if(isset($it['price']))$s[]="price=".floatval($it['price']);if(isset($it['quantity']))$s[]="quantity=".intval($it['quantity']);
        if(!empty($s)){$s[]="date_modified=NOW()";$this->q("UPDATE ".$this->t('product')." SET ".implode(',',$s)." WHERE product_id={$pid}");}
        if(isset($it['discounts'])&&is_array($it['discounts'])){$this->q("DELETE FROM ".$this->t('product_discount')." WHERE product_id={$pid}");
        foreach($it['discounts'] as $d){$gi=intval($d['customer_group_id']);$qt=intval($d['quantity']);$pr=floatval($d['price']);$pp=isset($d['priority'])?intval($d['priority']):0;$ds=isset($d['date_start'])?$this->e($d['date_start']):"'0000-00-00'";$de=isset($d['date_end'])?$this->e($d['date_end']):"'".date('Y-m-d',strtotime('+1 year'))."'";
        $this->q("INSERT INTO ".$this->t('product_discount')." (product_id,customer_group_id,quantity,priority,price,date_start,date_end) VALUES ({$pid},{$gi},{$qt},{$pp},{$pr},{$ds},{$de})");}}$n++;}
        $this->j(array('ok'=>true,'updated'=>$n));
    }

    // === BATCH QUANTITY ===
    private function doBatchQuantity(){
        $b=$this->body();$items=isset($b['items'])?$b['items']:array();$n=0;
        if(count($items)>50){$cs=array();$ids=array();foreach($items as $it){$pid=intval($it['product_id']);$qt=intval($it['quantity']);if(!$pid)continue;$cs[]="WHEN {$pid} THEN {$qt}";$ids[]=$pid;}
        if(!empty($ids)){$this->q("UPDATE ".$this->t('product')." SET quantity=CASE product_id ".implode(' ',$cs)." ELSE quantity END WHERE product_id IN (".implode(',',$ids).")");$n=count($ids);}
        }else{foreach($items as $it){$pid=intval($it['product_id']);$qt=intval($it['quantity']);if(!$pid)continue;$this->q("UPDATE ".$this->t('product')." SET quantity={$qt} WHERE product_id={$pid}");$n++;}}
        $this->j(array('ok'=>true,'updated'=>$n));
    }

    // === BATCH SPECIALS ===
    private function doBatchSpecials(){
        $b=$this->body();if(isset($b['clear_groups'])){$gi=array_map('intval',$b['clear_groups']);$this->q("DELETE FROM ".$this->t('product_special')." WHERE customer_group_id IN (".implode(',',$gi).")");}
        $n=0;if(isset($b['items'])){$gids=isset($b['customer_group_ids'])?$b['customer_group_ids']:array(1);
        foreach($b['items'] as $it){$pid=intval($it['product_id']);if(!$pid)continue;$pr=floatval($it['price']);$ds=isset($it['date_start'])?$this->e($it['date_start']):"'".date('Y-m-d')."'";$de=isset($it['date_end'])?$this->e($it['date_end']):"'".date('Y-m-d',strtotime('+1 day'))."'";$pp=isset($it['priority'])?intval($it['priority']):0;
        foreach($gids as $g){$g=intval($g);$this->q("INSERT INTO ".$this->t('product_special')." (product_id,customer_group_id,priority,price,date_start,date_end) VALUES ({$pid},{$g},{$pp},{$pr},{$ds},{$de})");$n++;}}}
        $this->j(array('ok'=>true,'inserted'=>$n));
    }

    // === CATEGORY CREATE ===
    private function doCategoryCreate(){
        $b=$this->body();$c=$this->req($b,'category');if(!$c)return;$pi=isset($c['parent_id'])?intval($c['parent_id']):0;
        $f=array('parent_id'=>$pi,'top'=>isset($c['top'])?intval($c['top']):0,'column'=>isset($c['column'])?intval($c['column']):1,'sort_order'=>isset($c['sort_order'])?intval($c['sort_order']):0,'status'=>isset($c['status'])?intval($c['status']):1,'date_added'=>date('Y-m-d H:i:s'),'date_modified'=>date('Y-m-d H:i:s'));
        foreach(array('noindex','uuid','image') as $x){if(isset($c[$x]))$f[$x]=$c[$x];}
        $cs=array();$vs=array();foreach($f as $k=>$v){$cs[]='`'.$k.'`';$vs[]=is_int($v)?$v:$this->e($v);}
        $this->db->query("INSERT INTO ".$this->t('category')." (".implode(',',$cs).") VALUES (".implode(',',$vs).")");$cid=$this->db->getLastId();
        $si=isset($c['store_id'])?intval($c['store_id']):0;$this->q("INSERT INTO ".$this->t('category_to_store')." (category_id,store_id) VALUES ({$cid},{$si})");
        if(isset($b['descriptions'])){foreach($b['descriptions'] as $d){$li=intval($d['language_id']);$this->q("INSERT INTO ".$this->t('category_description')." (category_id,language_id,name,description,meta_title,meta_description,meta_keyword) VALUES ({$cid},{$li},".$this->e(isset($d['name'])?$d['name']:'').",".$this->e(isset($d['description'])?$d['description']:'').",".$this->e(isset($d['meta_title'])?$d['meta_title']:'').",".$this->e(isset($d['meta_description'])?$d['meta_description']:'').",".$this->e(isset($d['meta_keyword'])?$d['meta_keyword']:'').")");}}
        $pp=$pi>0?$this->fa("SELECT path_id FROM ".$this->t('category_path')." WHERE category_id={$pi} ORDER BY level ASC"):array();$lv=0;
        foreach($pp as $r){$this->q("INSERT INTO ".$this->t('category_path')." (category_id,path_id,level) VALUES ({$cid},".intval($r['path_id']).",{$lv})");$lv++;}
        $this->q("INSERT INTO ".$this->t('category_path')." (category_id,path_id,level) VALUES ({$cid},{$cid},{$lv})");
        if(isset($b['seo_urls']))$this->seoSave('category_id',$cid,$b['seo_urls']);
        $this->j(array('ok'=>true,'category_id'=>$cid));
    }

    // === CATEGORY UPDATE ===
    private function doCategoryUpdate(){
        $b=$this->body();$cid=intval($this->req($b,'category_id'));if(!$cid)return;
        if(isset($b['fields'])){$s=array();foreach($b['fields'] as $k=>$v){if($k==='category_id')continue;if(is_null($v))$s[]="`{$k}`=NULL";elseif(is_int($v)||is_float($v))$s[]="`{$k}`={$v}";else $s[]="`{$k}`=".$this->e($v);}if(!empty($s)){$s[]="`date_modified`=NOW()";$this->q("UPDATE ".$this->t('category')." SET ".implode(',',$s)." WHERE category_id={$cid}");}}
        if(isset($b['descriptions'])){foreach($b['descriptions'] as $d){$li=intval($d['language_id']);$s=array();foreach(array('name','description','meta_title','meta_description','meta_keyword','meta_h1') as $c){if(isset($d[$c]))$s[]="`{$c}`=".$this->e($d[$c]);}if(!empty($s))$this->q("UPDATE ".$this->t('category_description')." SET ".implode(',',$s)." WHERE category_id={$cid} AND language_id={$li}");}}
        if(isset($b['seo_urls'])){$this->seoDel('category_id',$cid);$this->seoSave('category_id',$cid,$b['seo_urls']);}
        $this->j(array('ok'=>true,'category_id'=>$cid));
    }

    // === MANUFACTURER ===
    private function doManufacturerSave(){
        $b=$this->body();$nm=$this->req($b,'name');if(!$nm)return;$mid=isset($b['manufacturer_id'])?intval($b['manufacturer_id']):0;
        if($mid>0){$s=array("name=".$this->e($nm));foreach(array('image','noindex','sort_order','uuid') as $x){if(isset($b[$x]))$s[]="`{$x}`=".$this->e($b[$x]);}$this->q("UPDATE ".$this->t('manufacturer')." SET ".implode(',',$s)." WHERE manufacturer_id={$mid}");
        }else{$c=array('name');$v=array($this->e($nm));foreach(array('image','noindex','sort_order','uuid') as $x){if(isset($b[$x])){$c[]='`'.$x.'`';$v[]=$this->e($b[$x]);}}$this->db->query("INSERT INTO ".$this->t('manufacturer')." (".implode(',',$c).") VALUES (".implode(',',$v).")");$mid=$this->db->getLastId();$si=isset($b['store_id'])?intval($b['store_id']):0;$this->q("INSERT INTO ".$this->t('manufacturer_to_store')." (manufacturer_id,store_id) VALUES ({$mid},{$si})");}
        $this->j(array('ok'=>true,'manufacturer_id'=>$mid));
    }

    // === ORDERS LIST ===
    private function doOrdersList(){
        $lim=isset($this->request->get['limit'])?min(intval($this->request->get['limit']),200):20;$off=isset($this->request->get['offset'])?intval($this->request->get['offset']):0;
        $w=array("o.order_status_id>0");
        if(isset($this->request->get['status'])&&$this->request->get['status']!=='')$w[]="o.order_status_id=".intval($this->request->get['status']);
        if(isset($this->request->get['date_from'])&&$this->request->get['date_from']!=='')$w[]="o.date_added>=".$this->e($this->request->get['date_from'].' 00:00:00');
        if(isset($this->request->get['date_to'])&&$this->request->get['date_to']!=='')$w[]="o.date_added<=".$this->e($this->request->get['date_to'].' 23:59:59');
        $ws=implode(' AND ',$w);$tot=$this->fr("SELECT COUNT(*) as cnt FROM ".$this->t('order')." o WHERE {$ws}");
        $ords=$this->fa("SELECT o.order_id,o.firstname,o.lastname,o.email,o.telephone,o.total,o.currency_code,o.order_status_id,o.date_added,o.date_modified,o.payment_method,o.shipping_method,o.comment,os.name as status_name FROM ".$this->t('order')." o LEFT JOIN ".$this->t('order_status')." os ON os.order_status_id=o.order_status_id AND os.language_id=1 WHERE {$ws} ORDER BY o.order_id DESC LIMIT {$off},{$lim}");
        $this->j(array('ok'=>true,'total'=>$tot?intval($tot['cnt']):0,'orders'=>$ords));
    }

    // === ORDER GET ===
    private function doOrderGet(){
        $b=$this->body();$oid=intval($this->req($b,'order_id'));if(!$oid)return;
        $o=$this->fr("SELECT o.*,os.name as status_name FROM ".$this->t('order')." o LEFT JOIN ".$this->t('order_status')." os ON os.order_status_id=o.order_status_id AND os.language_id=1 WHERE o.order_id={$oid}");
        if(!$o){$this->err('Not found',404);return;}
        $o['products']=$this->fa("SELECT op.*,p.sku,p.model FROM ".$this->t('order_product')." op LEFT JOIN ".$this->t('product')." p ON p.product_id=op.product_id WHERE op.order_id={$oid}");
        $o['totals']=$this->fa("SELECT * FROM ".$this->t('order_total')." WHERE order_id={$oid} ORDER BY sort_order");
        $o['history']=$this->fa("SELECT oh.*,os.name as status_name FROM ".$this->t('order_history')." oh LEFT JOIN ".$this->t('order_status')." os ON os.order_status_id=oh.order_status_id AND os.language_id=1 WHERE oh.order_id={$oid} ORDER BY oh.date_added");
        $st=$this->fr("SHOW TABLES LIKE '".$this->t('simple_order')."'");if($st)$o['simple_fields']=$this->fa("SELECT * FROM ".$this->t('simple_order')." WHERE order_id={$oid}");
        $this->j(array('ok'=>true,'order'=>$o));
    }

    // === CACHE CLEAR ===
    private function doCacheClear(){
        $b=$this->body();$types=isset($b['types'])?$b['types']:array('all');$n=0;
        $dir=defined('DIR_CACHE')?DIR_CACHE:'';if(!$dir||!is_dir($dir)){$this->err('Cache dir not found',500);return;}
        foreach($types as $tp){$pat=$tp==='all'?'cache.*':'cache.'.$tp.'.*';$fs=glob($dir.$pat);if($fs)foreach($fs as $f){if(is_file($f)){unlink($f);$n++;}}}
        $this->j(array('ok'=>true,'cleared_count'=>$n));
    }

    // === HELPERS ===
    private function inDesc($pid,$descs){if(!is_array($descs))return;foreach($descs as $d){$li=intval($d['language_id']);$df=array('product_id'=>$pid,'language_id'=>$li);foreach(array('name','description','short_description','description_mini','tag','meta_title','meta_description','meta_keyword','meta_h1','image_description') as $c)$df[$c]=isset($d[$c])?$d[$c]:'';$cs=array();$vs=array();foreach($df as $k=>$v){$cs[]='`'.$k.'`';$vs[]=is_int($v)?$v:$this->e($v);}$this->q("INSERT INTO ".$this->t('product_description')." (".implode(',',$cs).") VALUES (".implode(',',$vs).")");}}
    private function inImg($pid,$imgs){if(!is_array($imgs))return;foreach($imgs as $i=>$im){$ip=$this->e($im['image']);$so=isset($im['sort_order'])?intval($im['sort_order']):$i;$x='';$xc='';if(isset($im['uuid'])){$xc=',uuid';$x=','.$this->e($im['uuid']);}if(isset($im['video'])){$xc.=',video';$x.=','.$this->e($im['video']);}if(isset($im['image_description'])){$xc.=',image_description';$x.=','.$this->e($im['image_description']);}$this->q("INSERT INTO ".$this->t('product_image')." (product_id,image,sort_order{$xc}) VALUES ({$pid},{$ip},{$so}{$x})");}}
    private function seoSave($ent,$id,$urls){if(!is_array($urls))return;$ha=!empty($this->fr("SHOW TABLES LIKE '".$this->t('url_alias')."'"));$hs=!empty($this->fr("SHOW TABLES LIKE '".$this->t('seo_url')."'"));$qv=$ent.'='.intval($id);foreach($urls as $u){$kw=$this->e($u['keyword']);if($ha)$this->q("INSERT INTO ".$this->t('url_alias')." (`query`,keyword) VALUES (".$this->e($qv).",{$kw})");if($hs){$li=isset($u['language_id'])?intval($u['language_id']):1;$si=isset($u['store_id'])?intval($u['store_id']):0;$this->q("INSERT INTO ".$this->t('seo_url')." (store_id,language_id,`query`,keyword) VALUES ({$si},{$li},".$this->e($qv).",{$kw})");}}}
    private function seoDel($ent,$id){$qv=$this->e($ent.'='.intval($id));$ha=!empty($this->fr("SHOW TABLES LIKE '".$this->t('url_alias')."'"));$hs=!empty($this->fr("SHOW TABLES LIKE '".$this->t('seo_url')."'"));if($ha)$this->q("DELETE FROM ".$this->t('url_alias')." WHERE `query`={$qv}");if($hs)$this->q("DELETE FROM ".$this->t('seo_url')." WHERE `query`={$qv}");}
}
