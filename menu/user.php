<?php
function instagramy_goodness_user(){
    $options = get_option('instagramy_goodness');
    $user = wp_get_current_user();
    if(isset($_GET['code'])){
        $code = sanitize_key($_GET['code']);
        $args = array("body" => array(
            "client_id"   =>    $options['client_id'],
            "client_secret" =>  $options['client_secret'],
            "grant_type"    =>  "authorization_code",
            "redirect_uri"  =>  instagramy_goodness_redirecturl(),
            "code"  =>  $code
        ));
        $tokenrawdata = wp_safe_remote_post("https://api.instagram.com/oauth/access_token",$args);
        $tokendata = json_decode($tokenrawdata["body"]);
        update_user_option($user->ID,"instagramy_goodness_token",$tokendata->access_token,true);
        update_user_option($user->ID,"instagramy_goodness_id",$tokendata->user->id,true);
        update_user_option($user->ID,"instagramy_goodness_username",$tokendata->user->username,true);
    }
    if(isset($_POST['submit']) && ($_POST['ig_form'] === 'settings')){
        check_admin_referer( 'ig_settings_'.$user->ID );
        $ig_user_day_post = sanitize_key((int)$_POST['day']);
        $ig_user_time_post = sanitize_key((int)$_POST['time']);
        $ig_user_format_post = sanitize_key($_POST['format']);
        $ig_user_linkto_post = sanitize_key($_POST['linkto']);
        $ig_user_captions_post = sanitize_key($_POST['use_captions']);
        $ig_user_title_post = sanitize_text_field($_POST['title']);
        update_user_option($user->ID,"instagramy_goodness_day",$ig_user_day_post, true);
        update_user_option($user->ID,"instagramy_goodness_time",$ig_user_time_post, true);
        update_user_option($user->ID,"instagramy_goodness_format",$ig_user_format_post, true);
        update_user_option($user->ID,"instagramy_goodness_linkto",$ig_user_linkto_post, true);
        update_user_option($user->ID,"instagramy_goodness_captions",$ig_user_captions_post, true);
        update_user_option($user->ID,"instagramy_goodness_title",$ig_user_title_post, true);
	    ?>
	    <div id="message" class="updated"><p><?php _e('Settings saved.');?></p></div>
        <?php
    } elseif(isset($_POST['submit']) && ($_POST['ig_form'] === 'createpost')){
	    check_admin_referer( 'ig_settings_'.$user->ID );
	    $createpost_status = instagramy_goodness_create_simple_post( $user->ID, false );
        if($createpost_status < instagramy_goodness_status::NOTOKEN){
            ?>
            <div id="message" class="updated"><p><?php _e('Draft created.',"instagramy_goodness");?></p></div>
        <?php
        } else {
            switch($createpost_status){
                case instagramy_goodness_status::NOTOKEN:
                    $message = __("It looks like you are not properly connected to Instagram.","instagramy_goodness");
                    break;
                case instagramy_goodness_status::SIDELOADERROR:
                    $message = __("There is a problem loading your pictures. This might be an indicator that this plugin might not be working on your system.","instagramy_goodness");
                    break;
                case instagramy_goodness_status::NOPHOTOS:
                    $message = __("There have been no new pictures since your last post.","instagramy_goodness");
                    break;
                case instagramy_goodness_status::NOTNOW:
                    $message = __("Not now, honey.","instagramy_goodness");
                    break;
                default:
                    $message = __("Something went wrong.","instagramy_goodness");
                    break;
            }
            ?>
                <div id="message" class="error"><p><?php echo $message ?></p></div>
            <?php
        }
    }
    $token = get_user_option("instagramy_goodness_token");
    ?>
<div class="wrap">
    <h2>Instagramy Goodness</h2>
    <?php
        if(!isset($options['client_id']) || !isset($options['client_secret'])) {
    ?>
    <p><?php _e("You should probably not be here.","instagramy_goodness"); ?></p>
    <?php
        echo "</div>\n"; // Because.
        return;
        }
    $instaurl = sprintf(
        "https://api.instagram.com/oauth/authorize/?client_id=%s&response_type=code&redirect_uri=%s",
        $options['client_id'],
        urlencode(instagramy_goodness_redirecturl())
    );
    ?>
    <p>
        <a href="<?php echo $instaurl ?>"><?php _e("Connect to Instagram.","instagramy_goodness"); ?></a>
    </p>
    <?php if(trim($token) != ""){
        $ig_username = get_user_option("instagramy_goodness_username");
        $ig_user_day = get_user_option("instagramy_goodness_day");
        $ig_user_time = get_user_option("instagramy_goodness_time");
        $ig_user_format = get_user_option("instagramy_goodness_format");
        $ig_user_linkto = get_user_option("instagramy_goodness_linkto");
        $ig_user_captions = get_user_option("instagramy_goodness_captions");
        if($ig_user_captions === false){
            $ig_user_captions = 1;
        }
        $ig_user_title = get_user_option("instagramy_goodness_title");
        ?>
    <p><?php printf(__("Good job! Instagram said your name is <em>%s</em>.","instagramy_goodness"),$ig_username);?></p>
    <?php
    $lastpost = get_user_option("instagramy_goodness_lastpost",$user->ID);
    if(!$lastpost){
	    $lastpost = time() - WEEK_IN_SECONDS;
    }

    $lastpost += 1;

    // We will set the time of the youngest picture here
    $lastpicturetime = 0;

    // And here we get the pictures
    $ig = new instagramy_goodness();
    $ig->setToken($token);
    $ig->setUserId($user->ID);
    $pictures = $ig->getOwnMedia(0, $lastpost);
	if(count($pictures->data) < 1){
		echo "<p>".__("You have no images for the next post.",'instagramy_goodness')."</p>";
	} else {
		echo "<h3>".__("Your next post should have these images:",'instagramy_goodness')."</h3>";
		foreach($pictures->data as $picture) {
            if(isset($picture->link)){
                $text = isset($picture->caption) ? $picture->caption->text : "";
                printf("<a href='%s'><img src='%s' alt='%s' title='%s'></a> \n",$picture->link,$picture->images->thumbnail->url,$text,$text);
            }
		}
	}
    ?>
    <h2><?php _e("Settings");?></h2>
    <form method="post">
        <h3><?php _e("Title");?></h3>
        <input type="text" name="title" value="<?php echo ($ig_user_title) ? $ig_user_title : "Instagramy Goodness"; ?>">
        <h3><?php _e("Format");?></h3>
        <select name="format" id="ig_format">
            <option value="gallery"<?php if($ig_user_format == "gallery") echo "selected"; ?>><?php _e("Gallery","instagramy_goodness"); ?></option>
            <option value="images"<?php if($ig_user_format == "images") echo "selected"; ?>><?php _e("Image list","instagramy_goodness"); ?></option>
            <!-- <option value="embed"<?php if($ig_user_format == "embed") echo "selected"; ?>><?php _e("Embeds","instagramy_goodness"); ?></option> -->
        </select>
        <div id="imagelistoptions">
            <h3><?php _e("Image list options","instagramy_goodness"); ?></h3>
            <table class='form-table'>
                <tr>
                    <th scope='row'><?php _e("Link to","instagramy_goodness"); ?>:</th>
                    <td>
                        <select name="linkto">
                            <option value="instagram"<?php if($ig_user_linkto == "instagram") echo "selected"; ?>>Instagram</option>
                            <option value="jpg"<?php if($ig_user_linkto == "jpg") echo "selected"; ?>>JPG</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope='row'><?php _e("Captions","instagramy_goodness"); ?>:</th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="use_captions" value="1"<?php if((int)$ig_user_captions == 1) echo "checked"; ?>> <?php _e("Use captions","instagramy_goodness"); ?></label><br>
                            <label><input type="radio" name="use_captions" value="0"<?php if((int)$ig_user_captions == 0) echo "checked"; ?>> <?php _e("Don't use captions","instagramy_goodness"); ?></label><br>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        <h3><?php _e("Post times","instagramy_goodness");?></h3>
        <table class='form-table'>
            <tr>
                <th scope='row'><?php _e("Post day","instagramy_goodness"); ?>:</th>
                <td>
                    <select name="day">
                        <option value="1"<?php if((int)$ig_user_day == 1) echo "selected"; ?>><?php _e("Monday"); ?></option>
                        <option value="2"<?php if((int)$ig_user_day == 2) echo "selected"; ?>><?php _e("Tuesday"); ?></option>
                        <option value="3"<?php if((int)$ig_user_day == 3) echo "selected"; ?>><?php _e("Wednesday"); ?></option>
                        <option value="4"<?php if((int)$ig_user_day == 4) echo "selected"; ?>><?php _e("Thursday"); ?></option>
                        <option value="5"<?php if((int)$ig_user_day == 5) echo "selected"; ?>><?php _e("Friday"); ?></option>
                        <option value="6"<?php if((int)$ig_user_day == 6) echo "selected"; ?>><?php _e("Saturday"); ?></option>
                        <option value="0"<?php if((int)$ig_user_day == 0) echo "selected"; ?>><?php _e("Sunday"); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope='row'><?php _e("Post time","instagramy_goodness"); ?>:</th>
                <td>
                    <select name="time">
                        <option value="0"<?php if((int)$ig_user_time == 0) echo "selected"; ?>><?php _e("Early morning","instagramy_goodness"); ?></option>
                        <option value="1"<?php if((int)$ig_user_time == 1) echo "selected"; ?>><?php _e("During the day","instagramy_goodness"); ?></option>
                        <option value="2"<?php if((int)$ig_user_time == 2) echo "selected"; ?>><?php _e("In the afternoon","instagramy_goodness"); ?></option>
                        <option value="3"<?php if((int)$ig_user_time == 3) echo "selected"; ?>><?php _e("During the evening","instagramy_goodness"); ?></option>
                    </select>
                </td>
            </tr>
        </table>
	    <input type="hidden" name="ig_form" value="settings">
        <?php wp_nonce_field( 'ig_settings_'.$user->ID ); ?>
        <?php submit_button('Update'); ?>
    </form>
    <?php
    } ?>
	<?php if(count($pictures->data) > 0 ){?>
	<h2><?php _e("Post now!","instagramy_goodness"); ?></h2>
		<form method="post">
			<input type="hidden" name="ig_form" value="createpost">
			<?php wp_nonce_field( 'ig_settings_'.$user->ID ); ?>
			<?php submit_button(__("Post now!","instagramy_goodness")); ?>
		</form>
	<?php } ?>
</div>
<?php
}