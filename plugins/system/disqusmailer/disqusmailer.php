<?php

defined( '_JEXEC' ) or die;

/**
 * Class plgContentDisqusmailer
 *
 * @author          Stuart Steedman
 * @version         1.0.0
 * @date            30 July 2016
 */
class plgSystemDisqusmailer extends JPlugin
{


	/** @var  int */
	private $item_id;

	/** @var string */
	private $cms_type = 'k2';

	/** @var  string */
	private $disqus_shortname;


	/**
	 * Injects the article ID into the already-present DISQUS javascript snippet
	 *
	 * @throws \Exception
	 */
	public function onAfterRender ()
	{
		if ( JFactory::getApplication()->isAdmin() ) return;

		$app  = JFactory::getApplication();
		$body = $app->getBody();

		if ( $this->hasDisqus( $body ) ) {
			$body = str_replace( '{{disqusarticleid}}', $this->item_id, $body );
		}

		$app->setBody( $body );
	}


	/**
	 * Processing for Joomla com_content articles
	 *
	 * @param string   $context
	 * @param stdClass $article
	 * @param mixed    $params
	 * @param int      $page
	 *
	 * @throws \Exception
	 */
	public function onContentPrepare ( $context, $article, &$params, $page = 0 )
	{
		if ( JFactory::getApplication()->isAdmin() ) return;

		# Ignore K2 articles, they get processed elsewhere
		if ( strpos( $context, 'k2' ) !== false ) return;

		$user_id = $article->created_by;
		$user    = JUser::getInstance( $user_id );

		$this->item_id  = $article->id;
		$this->cms_type = 'joomla';

		$author_email  = $user->email;
		$article_url   = JUri::root(false, $article->readmore_link);
		$article_title = $article->title;

		$this->loadScript( $this->item_id, $author_email, $article_url, $article_title );
	}


	/**
	 * Retrieves the K2 article details from its comment block trigger
	 *
	 * @param stdClass $item
	 * @param          $params
	 * @param          $limitstart
	 *
	 * @throws \Exception
	 */
	public function onK2CommentsBlock ( &$item, &$params, $limitstart )
	{
		if ( JFactory::getApplication()->isAdmin() ) return;

		if ( $item->id ) {
			$this->item_id  = $item->id;
			$this->cms_type = 'k2';
			$author_email   = $item->author->email;
			$article_url    = JUri::root(false, $item->link);
			$article_title  = $item->title;

			if ( strpos( $author_email, '@' ) !== false ) {
				$this->loadScript( $this->item_id, $author_email, $article_url, $article_title );
			}
		}
	}


	public function onAjaxDisqusmailer ()
	{
		$post = JFactory::getApplication()->input->post;

		# Get variables
		$article_id    = $post->getInt( 'article_id' );
		$article_title = @base64_decode( $post->getBase64( 'article_title' ) );
		$author_email  = $post->getString( 'author_email' );
		$text          = str_replace( "\n", '<br />', $post->get( 'text', '', 'RAW' ) );
		$url           = @base64_decode( $post->getBase64( 'article_url' ) );
		$recipients    = $this->params->get( 'send_to_admin' );
		$email_subject = $this->params->get( 'email_subject' );

		# Process additional emails, with very basic validation
		$addresses = [ ];
		if ( $recipients ) {
			$addresses = preg_split( '/(\,|\s|\;|\|\r\n|\n|\r)/m', $recipients );
			if ( is_array( $addresses ) && count( $addresses ) ) {
				foreach ( $addresses as $key => &$address ) {

					if ( strpos( $address, '@' ) === false ) {
						unset( $addresses[ $key ] );
					}

				}
			}
		}

		if ( $article_id && $author_email && $text ) {

			$template = $this->params->get( 'email_template' );

			$template = str_replace( '{article_title}', $article_title, $template );
			$template = str_replace( '{comment}', $text, $template );
			$template = str_replace( '{url}', $url, $template );
			$template = str_replace( '{date}', date( 'Y-m-d H:i:s' ), $template );

			$email_subject = trim( str_replace( '{article_title}', $article_title, $email_subject ) );

			$mail = JMail::getInstance();

			# Set recipients
			$mail->addRecipient( $author_email );
			if ( is_array( $addresses ) && count( $addresses ) ) {
				foreach ( $addresses as $address ) {
					$mail->addRecipient( $address );
				}
			}

			# Additional mail settings
			$mail->isHtml( true );
			$mail->setSubject( $email_subject );
			$mail->setBody( $template );

			# Send it!
			$mail->Send();
		}
	}


	/**
	 * Determines if the current HTML body has DISQUS Javascript in it, and scrapes it
	 * to variables to be used elsewhere
	 *
	 * @param string $body
	 *
	 * @return bool Returns TRUE if Disqus has been found
	 */
	private function hasDisqus ( $body )
	{

		$expression = '#disqus\_shortname\s*\=\s*.*\;#im';
		if ( preg_match( $expression, $body, $matches ) ) {
			$jse                    = $matches[ 0 ];
			$this->disqus_shortname = preg_replace( '#\D#m', '', $jse );
		}

		return false;
	}


	/**
	 * Loads the Javascript necessary to process DISQUS' onNewComment event,
	 * and then request a mail be sent
	 *
	 * @param int    $item_id
	 * @param string $author_email
	 * @param string $article_url
	 * @param string $article_title
	 */
	private function loadScript ( $item_id, $author_email, $article_url, $article_title )
	{
		if ( !defined( 'DISQUS_MAILER' ) ) {

			$ajax_url      = JUri::root() . "?option=com_ajax&plugin=disqusmailer&format=json";
			$article_url   = base64_encode( $article_url );
			$article_title = base64_encode( $article_title );

			$js = <<<JS
jQuery(function($) {

    window.disqus_config = function() {
        this.callbacks.onNewComment = [function(comment) { 
            // Comment parameter has 2 values
            // id = The id of the disqus comment
            // text = the comment message
            
            data = comment;
            
            // Add in our article ID parameter so we can locate the article by ID
            data.article_id = $item_id;
            data.author_email = '$author_email';
            data.article_url = '$article_url';
            data.article_title = '$article_title';

            $.ajax({
                method: 'POST',
                url: '$ajax_url',
                data: data,
                timeout: 10000
            });
        }];
    }

    var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
    dsq.src = '//' + $this->disqus_shortname + '.disqus.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
});
JS;

			JFactory::getDocument()->addScriptDeclaration( $js );
			define( 'DISQUS_MAILER', 1 );
		}
	}


}