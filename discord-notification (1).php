<?php
/*
Plugin Name: Discord Bildirim - Kategori Destekli
Description: Yeni yazılar yayımlandığında kategoriye göre rol etiketleri ile birlikte Discord'a bildirim gönderir.
Version: 3.2
Author: seul
*/

if (!defined('ABSPATH')) exit;

class DSN_Discord_Notification {
    private $a1, $a2, $a3, $a4, $a5;
    
    public function __construct() {
        $this->a1 = 'publish_post';
        $this->a2 = 'admin_menu';
        $this->a3 = 'admin_init';
        $this->a4 = 'dsn_webhook_url';
        $this->a5 = 'dsn_category_roles';
        
        add_action($this->a1, [$this, 'x1']);
        add_action($this->a2, [$this, 'x2']);
        add_action($this->a3, [$this, 'x3']);
    }
    
    public function x1($p) {
        if (wp_is_post_revision($p)) return;
        
        error_log("DSN Debug: Post ID $p için bildirim başlatıldı");
        
        $q = get_post($p);
        if (!$q) {
            error_log("DSN Debug: Post bulunamadı - ID: $p");
            return;
        }
        
        $r = $q->post_title;
        $s = get_permalink($p);
        $t = get_the_category($p);
        $u = array_map(function($v) { return $v->name; }, $t);
        $w = !empty($t) ? $t[0]->name : '';
        $z = get_option($this->a4, '');
        
        if (empty($z)) {
            error_log("DSN Debug: Webhook URL boş!");
            return;
        }
        
        error_log("DSN Debug: Webhook URL mevcut - " . substr($z, 0, 50) . "...");
        
        // 5 saniye bekleyip resim ara
        sleep(5);
        $x = $this->y1($q);
        
        // Resim bulunamazsa bildirimi gönderme
        if (empty($x)) {
            error_log("DSN Debug: Resim bulunamadı, bildirim gönderilmiyor");
            return;
        }
        
        $aa = $this->y2($t);
        $bb = get_option('dsn_everyone', 'false');
        
        error_log("DSN Debug: Bildirim gönderiliyor - Başlık: $r, Kategori: $w");
        
        $result = $this->y3($z, [
            'title' => $r,
            'permalink' => $s,
            'category' => $w,
            'categories' => $u,
            'image_url' => $x,
            'everyone' => $bb,
            'role_mentions' => $aa
        ]);
        
        error_log("DSN Debug: Bildirim sonucu - " . ($result ? 'Başarılı' : 'Başarısız'));
    }
    
    private function y1($q) {
        error_log("DSN Debug: Resim arama başladı - Post ID: " . $q->ID);
        
        // Featured image kontrolü
        if (has_post_thumbnail($q->ID)) {
            $featured = get_the_post_thumbnail_url($q->ID, 'large');
            if (!empty($featured)) {
                error_log("DSN Debug: Featured image bulundu: $featured");
                return $featured;
            }
        }
        
        // Post içeriğini al ve temizle
        $dd = $q->post_content;
        $dd = do_shortcode($dd); // Shortcode'ları işle
        $dd = apply_filters('the_content', $dd); // Content filter'ları uygula
        
        error_log("DSN Debug: İçerik uzunluğu: " . strlen($dd));
        
        // Çoklu resim pattern'ları dene
        $patterns = [
            '/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\'][^>]*>/i',
            '/<img[^>]+data-src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\'][^>]*>/i',
            '/<img[^>]+data-lazy-src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\'][^>]*>/i',
            '/https?:\/\/[^\s<>"\']+\.(jpg|jpeg|png|gif|webp)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $dd, $ee)) {
                $ff = $ee[1];
                
                // Relatif URL kontrolü
                if (strpos($ff, 'http') !== 0) {
                    $gg = get_site_url();
                    $ff = rtrim($gg, '/') . '/' . ltrim($ff, '/');
                }
                
                error_log("DSN Debug: İçerik resmi bulundu: $ff");
                return $ff;
            }
        }
        
        // WordPress galeri kontrolü
        if (has_shortcode($dd, 'gallery')) {
            $ll = get_post_gallery($q->ID, false);
            if (!empty($ll['src'])) {
                $gallery_img = current($ll['src']);
                error_log("DSN Debug: Galeri resmi bulundu: $gallery_img");
                return $gallery_img;
            }
        }
        
        // Attached media kontrolü
        $mm = get_attached_media('image', $q->ID);
        if (!empty($mm)) {
            $nn = array_shift($mm);
            $attached_img = wp_get_attachment_image_url($nn->ID, 'large');
            if (!empty($attached_img)) {
                error_log("DSN Debug: Attached media bulundu: $attached_img");
                return $attached_img;
            }
        }
        
        // Son çare: Post meta'dan resim ara
        $meta_keys = ['_thumbnail_id', 'featured_image', 'post_image'];
        foreach ($meta_keys as $key) {
            $meta_value = get_post_meta($q->ID, $key, true);
            if (!empty($meta_value)) {
                if (is_numeric($meta_value)) {
                    $meta_img = wp_get_attachment_image_url($meta_value, 'large');
                    if (!empty($meta_img)) {
                        error_log("DSN Debug: Meta resmi bulundu: $meta_img");
                        return $meta_img;
                    }
                }
            }
        }
        
        error_log("DSN Debug: Hiç resim bulunamadı!");
        return '';
    }
    
    private function y2($t) {
        $ee = get_option($this->a5, []);
        $ff = [];
        
        foreach ($t as $gg) {
            $hh = isset($ee[$gg->term_id]) ? $ee[$gg->term_id] : '';
            if (!empty($hh)) $ff[] = "<@&{$hh}>";
        }
        
        $ii = get_option('dsn_default_role', '');
        if (!empty($ii)) $ff[] = "<@&{$ii}>";
        
        return $ff;
    }
    
    private function y3($z, $jj) {
        $kk = $this->y4($jj);
        $ll = $this->y5($jj);
        
        $mm = ['content' => $kk, 'embeds' => [$ll]];
        
        error_log("DSN Debug: Discord payload - " . json_encode($mm));
        
        $response = wp_remote_post($z, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($mm),
            'timeout' => 30,
            'blocking' => true
        ]);
        
        if (is_wp_error($response)) {
            error_log("DSN Debug: WP Error - " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log("DSN Debug: Discord Response Code - $response_code");
        error_log("DSN Debug: Discord Response Body - $response_body");
        
        return $response_code >= 200 && $response_code < 300;
    }
    
    private function y4($jj) {
        $nn = "";
        
        if (!empty($jj['role_mentions'])) {
            $nn .= implode(' ', $jj['role_mentions']);
        }
        
        if ($jj['everyone'] && $jj['everyone'] === 'true') {
            $nn .= (!empty($nn) ? " " : "") . "**@everyone**";
        }
        
        return $nn;
    }
    
    private function y5($jj) {
        // Resimdeki formata uygun embed oluştur
        $category_text = !empty($jj['category']) ? $jj['category'] : 'Genel';
        
        $pp = [
            'title' => $jj['title'] . ' Bölüm Yayımlandı!',
            'description' => "Bölüm sitemize yüklenmiştir! Keyifli okumalar dileriz.\n\n[**" . $jj['title'] . "**](" . $jj['permalink'] . ")",
            'color' => 5814783, // Mor renk (resimdeki gibi)
            'timestamp' => date('c')
        ];
        
        // Resim varsa ekle (büyük resim olarak)
        if (!empty($jj['image_url'])) {
            $pp['image'] = ['url' => $jj['image_url']];
        }
        
        // Footer bilgisi (resimdeki gibi)
        $current_time = current_time('H:i');
        $pp['footer'] = [
            'text' => "Developer - sametmisadimglb • bugün saat $current_time"
        ];
        
        return $pp;
    }
    
    public function x2() {
        add_options_page(
            'Discord Bildirim Ayarları',
            'Discord Bildirim',
            'manage_options',
            'dsn-settings',
            [$this, 'x4']
        );
    }
    
    public function x3() {
        register_setting('dsn_settings', $this->a4);
        register_setting('dsn_settings', 'dsn_everyone');
        register_setting('dsn_settings', 'dsn_default_role');
        register_setting('dsn_settings', $this->a5);
    }
    
    public function x4() {
        if (isset($_POST['save_category_roles'])) {
            $rr = [];
            if (isset($_POST['category_roles']) && is_array($_POST['category_roles'])) {
                foreach ($_POST['category_roles'] as $ss => $tt) {
                    if (!empty($tt)) {
                        $rr[intval($ss)] = sanitize_text_field($tt);
                    }
                }
            }
            update_option($this->a5, $rr);
            echo '<div class="notice notice-success"><p>Kategori-rol eşleştirmeleri kaydedildi!</p></div>';
        }
        
        $uu = get_categories(['hide_empty' => false]);
        $vv = get_option($this->a5, []);
        ?>
        <div class="wrap">
            <h1>Discord Bildirim Ayarları</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('dsn_settings');
                do_settings_sections('dsn_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Discord Webhook URL</th>
                        <td>
                            <input type="url" name="<?php echo $this->a4; ?>" value="<?php echo esc_attr(get_option($this->a4)); ?>" class="large-text" required />
                            <p class="description">Discord kanalınızın webhook URL'sini girin.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Varsayılan Rol ID</th>
                        <td>
                            <input type="text" name="dsn_default_role" value="<?php echo esc_attr(get_option('dsn_default_role')); ?>" class="regular-text" />
                            <p class="description">Her duyuruda etiketlenecek varsayılan rol ID'si (örn: Tüm Seriler rolü).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">@everyone Etiketi</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dsn_everyone" value="true" <?php checked(get_option('dsn_everyone'), 'true'); ?> />
                                Yeni bölüm bildirimlerinde @everyone etiketini kullan
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Kategori-Rol Eşleştirmeleri</h2>
            <p>Her kategoriye özel rol ID'si atayabilirsiniz. Boş bırakılan kategoriler için sadece varsayılan rol etiketlenir.</p>
            
            <form method="post" action="">
                <table class="form-table">
                    <?php foreach ($uu as $ww): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($ww->name); ?></th>
                        <td>
                            <input type="text" 
                                   name="category_roles[<?php echo $ww->term_id; ?>]" 
                                   value="<?php echo esc_attr($vv[$ww->term_id] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="Rol ID'sini girin" />
                            <p class="description">Bu kategorideki yazılar için özel rol ID'si.</p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <input type="hidden" name="save_category_roles" value="1" />
                <?php submit_button('Kategori-Rol Eşleştirmelerini Kaydet', 'secondary'); ?>
            </form>
            
            <hr>
            
            <h2>Test Bildirimi Gönder</h2>
            <p>Son yayınlanan yazı için test bildirimi göndermek istiyorsanız aşağıdaki butona tıklayın.</p>
            <form method="post" action="">
                <input type="hidden" name="dsn_test_notification" value="1" />
                <?php wp_nonce_field('dsn_test_notification', 'dsn_test_nonce'); ?>
                <input type="submit" class="button-secondary" value="Test Bildirimi Gönder" />
            </form>
            
            <?php
            if (isset($_POST['dsn_test_notification']) && wp_verify_nonce($_POST['dsn_test_nonce'], 'dsn_test_notification')) {
                $xx = get_posts(['numberposts' => 1, 'post_status' => 'publish']);
                if ($xx) {
                    $result = $this->x1($xx[0]->ID);
                    if ($result !== false) {
                        echo '<div class="notice notice-success"><p>Test bildirimi gönderildi! Log dosyasını kontrol edin.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Test bildirimi gönderilemedi! Log dosyasını kontrol edin.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>Test edilecek yazı bulunamadı!</p></div>';
                }
            }
            ?>
            
            <hr>
            
            <h2>Debug Bilgileri</h2>
            <p>Bildirim gönderilmeme sorunu yaşıyorsanız:</p>
            <ol>
                <li>WordPress debug loglarını kontrol edin</li>
                <li>Webhook URL'nin doğru olduğundan emin olun</li>
                <li>Discord kanalında bot yetkilerini kontrol edin</li>
                <li>Test bildirimi göndererek logları inceleyin</li>
            </ol>
            
            <hr>
            
            <h2>Rol ID'si Nasıl Bulunur?</h2>
            <ol>
                <li>Discord'da Developer Mode'u açın (User Settings > Advanced > Developer Mode)</li>
                <li>Sunucunuzda Sunucu Ayarları > Roller'e gidin</li>
                <li>Rol isminin üzerine sağ tıklayın ve "ID'yi Kopyala" seçin</li>
                <li>Bu ID'yi yukarıdaki alanlara yapıştırın</li>
            </ol>
        </div>
        <?php
    }
}

new DSN_Discord_Notification();
?>