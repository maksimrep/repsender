<?php

/**
 * Class RepsenderSubscribing
 */
final class RepsenderSubscribing{

    /**
     * Global variable wpdb
     * @var QM_DB
     */
    private $wpdb;

    /**
     * Table name
     * @var string
     */
    private $tabel_name;
    
    
    /**
     * Subscribing constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tabel_name = $wpdb->get_blog_prefix() . 'repsender_subscribers';
    }

    /**
     * Check if subscriber exist
     * @param string $email
     * @return bool
     */
    public function is_SubscriberCreated(string $email): bool
    {
        $is_email = $this->wpdb->get_row( "SELECT * FROM $this->tabel_name WHERE email = '$email'" );
        if($is_email){
            return true;
        }
        return false;
    }

    /**
     * Create subscriber
     * @param string $email
     * @param string $language
     * @param string $ip
     * @param string $name
     * @return int
     * 
     */
    public function createSubscriber($email, $language, $ip = '', $name = 'user'):int
    {

        if ( $this->is_SubscriberCreated($email) )
            return 0;
		    
        $insert = $this->wpdb->insert( 
            $this->tabel_name,
            [
                'name' => $name,
                'email' => $email,
                'language' => $language,
                'ip' => $ip,
                'token' => sha1( $email . time() )
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
        
        if($insert)
            return $this->wpdb->insert_id;
        return 0;
    }

    /**
     * Get list subscribers
     * @param string
     * @return array
     */
    public function getActiveSubscribersFoLanguage(string $language): array
    {
        $subscribers = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name WHERE language = '$language' AND status = true",
            'ARRAY_A'
        );
        if($subscribers)
            return $subscribers;
        return [];
    }
    
    /**
     * Get subscriber for id
     * @param int $id
     * @return array
     */
    public function getSubscriber(int $id):array
    {
        $subscriber = $this->wpdb->get_row(
            "SELECT * FROM $this->tabel_name WHERE id = '$id'",
            'ARRAY_A'
        );
        if($subscriber)
            return $subscriber;
        return [];
    }


    /**
     * Send mail
     * @param int $id
     */
    public function sendMail(int $user_id){

        $subscriber = $this->getSubscriber($user_id);
        $template = $this->getMailTemplate( $subscriber['language'] );

        if($subscriber && $template){

            $email = $subscriber['email'];
            $type = $template['type'];
            $from = $template['from'];
            $subject = $template['subject'];
            $description = $template['description'];
            $message = $template['body'];

            $headers = array();
            if($from)
                array_push($headers, 'From: '.$from);
            if($type == "html"){
                array_push($headers, 'content-type: text/html');
                $description =  '<div style="display:none;font-size:1px;color:transporent;line-height:0px;max-width:0px;opacity:0;overflow:hidden;">'.
                                $description.
                                "</div>";
                $message = $description.$message;
            }

            $activation_link = site_url().'/wp-json/repsender/v1/subscribing/activate/'.$subscriber['id'].'/'.$subscriber['token'];
            $unsubscribe_link = site_url().'/wp-json/repsender/v1/subscribing/delete/'.$subscriber['id'].'/'.$subscriber['token'];

            $short_codes = array();
            array_push( $short_codes, "/".get_shortcode_regex( array('_site_url'))."/" );
            array_push( $short_codes, "/".get_shortcode_regex( array('_repsender_subscribe_link'))."/" );
            array_push( $short_codes, "/".get_shortcode_regex( array('_repsender_unsubscribe_link'))."/" );
            $message = preg_replace( $short_codes, array(site_url(), $activation_link, $unsubscribe_link), $message );
            
            $status_send = wp_mail( $email, $subject, do_shortcode($message), $headers );

            if($status_send)
                return true;
        }
        return false;
    }

    /**
     * Get email template by language code
     * @param string $lang_code
     * @return array
     */
    public function getMailTemplate(string $lang_code):array
    {
        $template_arr = [];
        $prefix = 'options_repsender_subscribing_mail_templates_arr_subscribing';
        $template_count = get_option($prefix, ""); 

        if($template_count){
            for($i = 0; $i < $template_count; $i++){
                $prefix_i = $prefix.'_'.$i.'_';

                if( $lang = get_option($prefix_i."lang", "") ){
                    if($lang == $lang_code){

                        $template_arr = [
                            'type' => 'text',
                            'from' => '',
                            'subject' => '',
                            'description' => '',
                            'body' => ''
                        ];

                        if( $content_type = get_option($prefix_i."buyer_email_content_type", "") )
                            $template_arr['type'] = $content_type;
                        if( $from = get_option($prefix_i."from_email_address", "") )
                            $template_arr['from'] = $from;
                        if( $subject = get_option($prefix_i."buyer_email_subject", "") )
                            $template_arr['subject'] = $subject;
                        if( $description = get_option($prefix_i."buyer_email_description", "") )
                            $template_arr['description'] = $description;
                        if( $body = get_option($prefix_i."buyer_email_body", "") )
                            $template_arr['body'] = $body;
                    }
                }
            }
        }

        return $template_arr; 
    }

    /**
     * Activate profile Subscriber
     * @param int $id
     * @return bool
     */
    public function activateSubscriber(int $id):bool
    {
        $activate = $this->wpdb->update(
            $this->tabel_name,
            [
                'status' => true,
                'token' => sha1( $id . time() ),
                'updated' => (int) current_time('timestamp')
            ],
            [
                'id' => $id
            ],
            ['%d','%s'],
            ['%d']
        );
        if($activate !== false)
            return true;
        return false;
    }

    /**
     * Get welcome page url
     * @param string $lang
     * @return string
     */
    public function getWelcomePageUrl(string $language_code):string
    {
        $prefix = "options_subscribing_welcome_repeater";
        $redirect_page = get_option($prefix, false);
        $page_url = '';

        if($redirect_page){
            for($i = 0; $i <= $redirect_page; $i++){
                $prefix_i = $prefix.'_'.$i.'_';

                if( $lang = get_option($prefix_i."lang", "") ){
                    if($lang == $language_code){
                        $page_url = get_option($prefix_i."page", "");
                        if(!is_null($page_url['url']))
                            return $page_url['url'];
                    }
                }
            }
        } 

        return home_url();
    }

    /**
     * Deactivate profile Subscriber
     * @param int $id
     * @return bool
     */
    public function deactivateSubscriber(int $id):bool
    {
        $newsletter = new RepsenderNewsletter();
        $mails = count($newsletter->getRecipientsBySubscriberID($id));
        if( ($mails && $newsletter->deleteRecipientForSubscriberID($id)) || !$mails ){
            if($this->deleteSubscriber($id))
                return true;
        }
        return false;
    }

    /**
     * Delete Subscriber
     * @param int $id
     * @return bool
     */
    protected function deleteSubscriber(int $id):bool
    {
        $deactivate = $this->wpdb->delete(
            $this->tabel_name,
            [
                'id' => $id
            ],
            ['%d']
        );
        if($deactivate)
            return true;
        return false;
    }

    /**
     * Get count subscribers
     * @return int
     */
    public function getCountSubscribers():int
    {
        $subscribers = $this->wpdb->get_results(
            "SELECT COUNT(*) FROM $this->tabel_name",
            'ARRAY_N'
        );
        if($subscribers)
            return $subscribers[0][0];
        return 0;
    }

    /**
     * Get subscribers
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getSubscribersPerPage(int $limit = 10, int $offset = 0):array
    {
        $subscribers = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name LIMIT $limit OFFSET $offset",
            'ARRAY_A'
        );
        if($subscribers)
            return $subscribers;
        return [];
    }

    /**
     * Get unsubscibing link
     * @param int $user_id
     * @return stiring url
     */
    public function getUnsubscribingLink(int $user_id):string
    {
        $unsubscribe_link = '';
        $subscriber = $this->getSubscriber($user_id);
        $unsubscribe_link = site_url().'/wp-json/repsender/v1/subscribing/delete/'.$subscriber['id'].'/'.$subscriber['token'];
        return $unsubscribe_link;
    }

}
