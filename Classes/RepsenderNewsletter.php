<?php

/**
 * Class RepsenderNewsletter
 */
final class RepsenderNewsletter{

    /**
     * Global variable wpdb
     * @var QM_DB
     */
    private $wpdb;

    /**
     * Table name emails
     * @var string
     */
    private $tabel_name_emails;

    /**
     * Table name mailings
     * @var string
     */
    private $tabel_name_mailings;

    /**
     * RepsenderSubscribing object
     */
    public $repsendersubscribing;

    /**
     * Limit sending for mail per minute
     */
    private $limit_in_time;

    /**
     * Subscribing constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tabel_name_emails = $wpdb->get_blog_prefix() . 'repsender_emails';
        $this->tabel_name_mailings = $wpdb->get_blog_prefix() . 'repsender_mailings';

        $this->repsendersubscribing = new RepsenderSubscribing();

        $this->limit_in_time = get_option("options_subscribing_number_mail_per_minute", 100);
    }

    /**
     * Create email
     * @param array $data
     * @return int
     */
    public function createEmail(array $data){
        try{
            $insert_result = $this->wpdb->insert( 
                $this->tabel_name_emails,
                [
                    'language' => $data['language'],
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'message' => $data['message'],
                    'total' => $data['total'],
                    'post_date' => date( 'Y-m-d H:i:s', time() )
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s'
                ]
            );
            if($insert_result)
                return $this->wpdb->insert_id;
            return 0;
        }catch(Exception $e){
            return 0;
        }
    }

    /**
     * Get last date mailing
     * @return string
     */
    public function getLastSendDate():string
    {
        $lastDate = $this->wpdb->get_var(
            "SELECT post_date FROM $this->tabel_name_emails ORDER BY id DESC LIMIT 1"
        );
        if($lastDate)
            return $lastDate;
        return "";
    }

    /**
     * Get last date mailing by language
     * @param string $lang
     * @return string
     */
    public function getLastSendDateByLang(string $lang):string
    {
        $lastDate = $this->wpdb->get_var(
            "SELECT post_date FROM $this->tabel_name_emails WHERE language = '$lang' ORDER BY id DESC LIMIT 1"
        );
        if($lastDate)
            return $lastDate;
        return "";
    }
    

    /**
     * Set list with recipient
     * @param int email_id
     * @param int user_id
     * @return bool
     */
    public function setRecipient(int $email_id, int $user_id):bool
    {
        if(!$this->is_recipientExisted($email_id, $user_id)){
            $insert_result = $this->wpdb->insert( 
                $this->tabel_name_mailings,
                [
                    'email_id' => $email_id,
                    'user_id' => $user_id
                ],
                [
                    '%d',
                    '%d'
                ]
            );
            if(!is_null($insert_result))
                return true;
        }
        return false;
    }

    /**
     * Check Recipient
     * @param int email_id
     * @param int user_id
     * @return bool
     */
    public function is_recipientExisted(int $email_id, int $user_id){
        $recipient = $this->wpdb->get_row(
            "SELECT * FROM $this->tabel_name_mailings WHERE email_id = '$email_id' AND user_id = '$user_id'"
        );
        if(!is_null($recipient))
            return true;
        return false;
    }

    /**
     * Start mailing newsletter
     * @return bool
     */
    public function startSendingNewsletter():bool
    {
        $count_send = 0;
        $recipients = $this->getActiveRecipients();
        $newsletters = $this->getActiveNewsletters();

        if($newsletters){
            foreach($newsletters as $newsletter){

                if($count_send == $this->limit_in_time)
                    break;

                if( $mailings = wp_list_filter( $recipients, [ 'email_id' => $newsletter['id'] ] ) ){
                    
                    if( count($mailings) > ($this->limit_in_time - $count_send) )
                        $mailings = array_slice( $mailings, 0, ($this->limit_in_time - $count_send) );
                    
                    foreach($mailings as $mailing){
                        
                        if($this->sendNewsletter($newsletter['id'], $mailing['user_id'])){
                            $this->updateMailing(
                                $mailing['email_id'],
                                $mailing['user_id'],
                                ['status_send' => true, 'time_send' => time()],
                                ['%d','%d']
                            );
                        }else{
                            $this->updateMailing(
                                $mailing['email_id'],
                                $mailing['user_id'],
                                ['error' => 'error', 'time_send' => time()],
                                ['%s','%d']
                            );
                        }

                        $count_send++;
                    }
                }
            }
        }

        return $this->updateNewsletters();
    }

    /**
     * Get active recipient records
     * @return array
     */
    protected function getActiveRecipients(){
        $recipients = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name_mailings WHERE status_send = false",
            'ARRAY_A'
        );
        if($recipients)
            return $recipients;
        return [];
    }

    /**
     * Get recipient records by Newsletter id
     * @param int $id
     * @return array
     */
    protected function getRecipientsBuNewsletter(int $id){
        $recipients = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name_mailings WHERE email_id = $id",
            'ARRAY_A'
        );
        if($recipients)
            return $recipients;
        return [];
    }

    /**
     * Get Active Newsletter records
     * @return array
     */
    public function getActiveNewsletters(){
        $newsletters = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name_emails WHERE status = 'new'",
            'ARRAY_A'
        );
        if($newsletters)
            return $newsletters;
        return [];
    }

    /**
     * Update Mailing record
     * @param int $email_id
     * @param int $user_id
     * @param array $data
     * @param array $format
     * @retrun bool
     */
    private function updateMailing(int $email_id, int $user_id, array $data, array $format):bool
    {
        $updated = $this->wpdb->update(
            $this->tabel_name_mailings,
            $data,
            [
                'email_id' => $email_id,
                'user_id' => $user_id
            ],
            $format,
            [
                '%d',
                '%d'
            ]
        );
        if($updated === false)
            return false;
        return true;
    }

    /**
     * Update Newsletter record
     * @return bool
     */
    protected function updateNewsletters():bool
    {
        $newsletters = $this->getActiveNewsletters();

        if($newsletters){
            foreach($newsletters as $newsletter){

                $recipients = $this->getRecipientsBuNewsletter($newsletter['id']);
                $data = [];
                $format = [];

                if($recipients){

                    $mailings_sent = count(wp_list_filter( $recipients, [ 'status_send' => true ] ));
                    $mailings_sent_without_error = count(wp_list_filter( $recipients, [ 'status_send' => true, 'error' => ''] ));
                    $mailings_sent_with_error = $mailings_sent - $mailings_sent_without_error;
                    $opens = count( wp_list_filter( $recipients, [ 'status_send' => true, 'open' => true] ));
                    $sent = $newsletter['sent'] + ($mailings_sent - $mailings_sent_with_error);

                    $data = [
                        'total' => count($recipients),
                        'last_id' => array_pop($recipients)['user_id'],
                        'open_count' => $opens,
                        'sent' => $sent
                    ];
                    $format = ['%d', '%d', '%d', '%d'];

                    if($mailings_sent >= count($recipients) &&  $sent >= count($recipients)){
                        $data['status'] = 'sent';
                        $format[] .= '%s';
                    }else if(count($recipients) == $mailings_sent && count($recipients) > $sent){
                        $data['status'] = 'paused';
                        $format[] .= '%s';
                    }

                    
                }else{
                    $data['status'] = 'paused';
                    $format[] .= '%s';
                }

                    $this->wpdb->update(
                        $this->tabel_name_emails,
                        $data,
                        [
                            'id' => $newsletter['id'],
                        ],
                        $format,
                        [
                            '%d'
                        ]
                    );

            }
        }
        return true;
    }

    /**
     * Create Newsletter
     * @param RepsenderSubscribing
     */
    public function createNewsletters(RepsenderSubscribing $subscribing){

        $language_arr = ['en'];
        if( function_exists('pll_languages_list') )
            $language_arr = pll_languages_list();
    
        foreach($language_arr as $lang){
            $args = [
                'numberposts'      => -1,
                'orderby'          => 'post_date',
                'order'            => 'DESC',
                'post_type'        => 'post',
                'post_status'      => 'publish',
                'suppress_filters' => true,
                'date_query' => ['after' => '24 hours ago'],
                'lang' => $lang
            ];
            $new_posts_for_mailing = get_posts( $args );
    
            if($new_posts_for_mailing){

                $lastSendDate = $this->getLastSendDateByLang($lang); 

                $post_links = '';
                foreach( $new_posts_for_mailing as $post ){
                    setup_postdata( $post );
                    if(strtotime($post->post_date) > strtotime($lastSendDate)){
                        if($post_links)
                            $post_links .= "<br>";
                        $post_links .= "<a href='".get_permalink($post->ID)."' target=\"_blank\">".$post->post_title."</a>";
                    }
                }
                wp_reset_postdata();
    
                $subscribers = $subscribing->getActiveSubscribersFoLanguage($lang);
    
                if($post_links){

                    $mail_template = $this->getMailTemplate($lang);

                    if($mail_template && !is_null($mail_template['body'])){

                        $short_codes = array();
                        array_push( $short_codes, "/".get_shortcode_regex( array('_repsender_newarticle_title')  )."/" );
                        $mail_template['body'] = preg_replace( $short_codes, array($post_links), $mail_template['body'] );
        
                        $data = [
                            'language' => $lang,
                            'subject' => (!is_null($mail_template['subject'])) ? $mail_template['subject'] : '',
                            'description' => (!is_null($mail_template['description'])) ? $mail_template['description'] : '',
                            'message' => $mail_template['body'],
                            'total' => count($subscribers)
                        ];
        
                        $newsletter_id = $this->createEmail($data);
        
                        if($newsletter_id && $subscribers){
                            foreach($subscribers as $subscriber){
                                $this->setRecipient($newsletter_id, $subscriber['id']);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get email template by language code
     * @param string $lang_code
     * @return array
     */
    public function getMailTemplate(string $lang_code):array
    {
        $template_arr = [];
        $prefix = 'options_repsender_subscribing_mail_templates_arr_mailing';
        $template_count = get_option($prefix, ""); 

        if($template_count){
            for($i = 0; $i < $template_count; $i++){
                $prefix_i = $prefix.'_'.$i.'_';

                if( $lang = get_option($prefix_i."lang", "") ){
                    if($lang == $lang_code){
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
     * Send newsletter
     * @param int
     * @param int
     * @return bool
     */
    public function sendNewsletter(int $email_id, int $user_id):bool
    {
        $subscriber = $this->repsendersubscribing->getSubscriber($user_id);
        $newsletter = $this->getNewsletter($email_id);
        $template = $this->getMailTemplate($newsletter['language']);

        if($subscriber && $newsletter){

            $email = $subscriber['email'];
            $type = $template['type'];
            $from = $template['from'];
            $subject = $newsletter['subject'];
            $description = $newsletter['description'];
            $message = $newsletter['message'];

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

            $short_codes = array();
            array_push( $short_codes, "/".get_shortcode_regex( array('_site_url'))."/" );
            array_push( $short_codes, "/".get_shortcode_regex( array('_repsender_unsubscribe_link'))."/" );
            $message = preg_replace( $short_codes, array(site_url(), $this->repsendersubscribing->getUnsubscribingLink($user_id)), $message );
            $status_send = wp_mail( $email, $subject, do_shortcode($message), $headers );

            if($status_send)
                return true;
        }
        return false;
    }

    /**
     * Get newsletter for id
     * @param int $id
     * @return array
     */
    public function getNewsletter(int $id):array
    {
        $newsletter = $this->wpdb->get_row(
            "SELECT * FROM $this->tabel_name_emails WHERE id = '$id'",
            'ARRAY_A'
        );
        if($newsletter)
            return $newsletter;
        return [];
    }

    /**
     * Delete Recipient for subscriber id
     * @param int $user_id
     * @return bool
     */
    public function deleteRecipientForSubscriberID(int $user_id):bool
    {
        $delete_recipients = $this->wpdb->delete(
            $this->tabel_name_mailings,
            [
                'user_id' => $user_id
            ],
            ['%d']
        );
        if($delete_recipients)
            return true;
        return false;
    }

    /**
     * Get recipient records by Subscriber id
     * @param int $user_id
     * @return array
     */
    public function getRecipientsBySubscriberID(int $user_id):array
    {
        $recipients = $this->wpdb->get_results(
            "SELECT * FROM $this->tabel_name_mailings WHERE user_id = $user_id",
            'ARRAY_A'
        );
        if($recipients)
            return $recipients;
        return [];
    }
}
