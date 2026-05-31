<?php

use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;

class td_social_api {

    private static $caching_time = 10800;  // cache expire time - default 10800 = 3 hours

	private $debug = false; // debug mode

    /**
     * - decode the json data
     * @param $url
     * @return array|mixed|object|string
     */
    private function get_json($url) {
        $td_json = json_decode(td_remote_http::get_page($url, __CLASS__), true);
        if ($td_json === null and json_last_error() !== JSON_ERROR_NONE) {
            td_log::log(__FILE__, __FUNCTION__, 'Error decoding the json', $td_json);
            return 'Error decoding the json';
        }
        return $td_json;
    }

    /**
     * - parse all characters in a string and retrieve only the numeric ones
     * @param $td_string
     * @return string
     */
    private function extract_numbers_from_string($td_string) {
        $buffy = '';
        foreach (str_split($td_string) as $td_char) {
            if (is_numeric($td_char)) {
                $buffy .= $td_char;
            }
        }
        return $buffy;
    }


    /**
     * - check the cache, update it if necessary and return the service data (number of likes, followers, etc.)
     * @param $service_id
     * @param $user_id
     * @param string $access_token
     * @return array|bool|int|string
     */
    public function get_social_counter( $service_id, $user_id, $access_token = '' ) {

        // in cache we save the service name followed by the user id (ex. facebook_envato)
        $service_cache_key = $service_id . '_' . $user_id;

        if ( td_remote_cache::is_expired(__CLASS__, $service_cache_key ) === true ) {

            // cache is expired - do a request
            $service_data = $this->get_service_data( $service_id, $user_id, $access_token );

            // check if the cache is already set and the current cached value is > 0
            if ( $service_data === 0 ) {
                $service_cached_data = td_remote_cache::get(__CLASS__, $service_cache_key);
                if ($service_cached_data !== false && $service_cached_data > 0){
                    // keep the cached value
                    $service_data = $service_cached_data;
                }
            }

            // set the cache - we don't use td_remote_cache::extend because td_remote_cache::is_expired returns true when the cache is not set
            td_remote_cache::set(__CLASS__, $service_cache_key , $service_data, self::$caching_time );

        } else {
            // cache is valid return the cached value
            $service_data = td_remote_cache::get(__CLASS__, $service_cache_key );
        }

        return $service_data;

    }


    /**
     * - retrieve the count for each service(likes, followers, etc)
     * @param $service_id
     * @param $user_id
     * @param $access_token
     * @return int
     */
    private function get_service_data( $service_id, $user_id, $access_token ) {
	    //$this->enable_debug_mode();
        $buffy_array = 0;

        switch ($service_id) {
            case 'facebook':

                $td_data = td_remote_http::get_page( "https://facebook.com/$user_id", __CLASS__ );

                if ( $td_data === false ) {

                	// log page html data not successful
                    td_log::log( __FILE__, __FUNCTION__, 'facebook page html data cannot be retrieved.', $user_id);

	                // try to get likes using fb business connected account if available
	                $page_likes_number = $this->get_page_data_from_connected_fb_account( 'fb', $user_id );
	                if ( $page_likes_number !== false ) {
		                $buffy_array = $page_likes_number;
	                }

                } else {
                    $pattern = '/PagesLikesCountDOMID[^>]+>(.*?)<\/a/s';
                    preg_match( $pattern, $td_data, $matches );

                    if ( !empty( $matches[1] ) ) {
                        $page_likes_number = $this->extract_numbers_from_string( strip_tags( $matches[1] ) );
                        $buffy_array = (int) $page_likes_number;

                        // log success
                        td_log::log( __FILE__, __FUNCTION__, 'facebook "' . $user_id . '" page likes data was retrieved successfully.', $buffy_array );

                    } else {

	                    // log no match found in page html data
                        td_log::log( __FILE__, __FUNCTION__, 'we haven\'t found a match in ' . $user_id . '\'s facebook page html data.', $td_data );

	                    // try to get page likes using fb business connected account if available
	                    $page_likes_number = $this->get_page_data_from_connected_fb_account( 'fb', $user_id );
	                    if ( $page_likes_number !== false ) {
		                    $buffy_array = $page_likes_number;
	                    }

                    }
                }

                break;

            case 'twitter':

                //$this->enable_debug_mode();

                // twitter_connected_account
                $td_op_tw_con_acc = td_options::get_array( 'td_twitter_connected_account');

                if ( !empty($td_op_tw_con_acc) ) {

                    // load twitter oauth api
                    require_once 'twitter-oauth.php';

                    // set connected twitter account data
                    $oauth_token = $td_op_tw_con_acc['oauth_token'];
                    $oauth_token_secret = $td_op_tw_con_acc['oauth_token_secret'];
                    $tw_user_id = $td_op_tw_con_acc['user_id'];
                    $screen_name = $td_op_tw_con_acc['screen_name'];

                    // user_id / connected user account name match check
                    if ( $user_id === $screen_name ) {

                        // make a TwitterOAuth instance with the connected twitter account oauth access tokens
                        $authenticated_connection = new TwitterOAuth( CONSUMER_KEY, CONSUMER_SECRET, $oauth_token, $oauth_token_secret );

                        // get connected user account details
                        try {

                            $authenticated_connection->setApiVersion('1.1');
                            $account_data = $authenticated_connection->get('account/verify_credentials' );

                            // get connected account followers_count
                            if ( isset($account_data->followers_count) ) {
                                $buffy_array = $account_data->followers_count;

                            // run through errors and add them to connected account data
                            } elseif ( isset($account_data->errors) ) {

                                // create errors array to connected account data
                                $td_op_tw_con_acc['errors'] = [];

                                foreach ( $account_data->errors as $error ) {

                                    // add error
                                    $td_op_tw_con_acc['errors'][] = [
                                        'error_code' => $error->code, // ex 89 > an invalid/expired token error code
                                        'error_message' => $error->message, // ex Invalid or expired token
                                    ];

                                }

                                // update connected account data option
                                td_options::update_array('td_twitter_connected_account', $td_op_tw_con_acc );

                            }

                            $debug_title = 'TwitterOAuth <span style="color: orangered">' . $screen_name . '</span> $account_data';

                            // td log success
                            td_log::log( __FILE__, __FUNCTION__,
                                'TwitterOAuth',
                                [
                                    'shortcode_user_id' => $user_id,
                                    'connected_account_screen_name' => $screen_name,
                                    '$account_data' => $account_data
                                ]
                            );

                        } catch ( TwitterOAuthException $ex ) {

                            $account_data = $ex;
                            $debug_title = 'TwitterOAuthException <span style="color: orangered">' . $screen_name . '</span> $ex';

                            // td log exception
                            td_log::log( __FILE__, __FUNCTION__,
                                'TwitterOAuthException',
                                [
                                    'shortcode_user_id' => $user_id,
                                    'connected_account_screen_name' => $screen_name,
                                    '$ex' => $ex
                                ]
                            );

                        }

                        //$this->debug( $account_data, $debug_title );

                    }

                }

                //$this->debug( $td_op_tw_con_acc, 'td_twitter_connected_account' );

                break;

			//case 'vimeo':
			//    $td_data = td_remote_http::get_page("http://vimeo.com/$user_id", __CLASS__);
			//    $pattern = "/<b class=\"stat_list_count\">(.*?)<\/b>(\s+)<span class=\"stat_list_label\">likes<\/span>/";
			//    preg_match($pattern, $td_data, $matches);
			//    if (!empty($matches[1])) {
			//        $buffy_array = (int) $matches[1];
			//    }
			//
			//    break;

            case 'youtube':

                $url = "https://www.googleapis.com/youtube/v3/channels?part=statistics&key=" . td_remote_video::get_yt_api_key();

                $search_id = str_replace("channel/", "", $user_id);

                if (strpos($user_id, "channel/") === 0) {
                    $url .= "&id=$search_id";
                } else {
                    $url .= "&forUsername=$search_id";
                }

                $subscriberCount = 0;
                $td_data = @$this->get_json($url);

                if (is_array($td_data) && !empty($td_data['items'][0]['statistics']['subscriberCount'])) {
                    $subscriberCount = $td_data['items'][0]['statistics']['subscriberCount'];
                }

                if (!empty($subscriberCount)) {
                    $buffy_array = (int) $subscriberCount;
                }
                break;

			//case 'googleplus':
			//    $td_data = @$this->get_json("https://www.googleapis.com/plus/v1/people/$user_id?key=AIzaSyA1hsdPPNpkS3lvjohwLNkOnhgsJ9YCZWw");
			//    if (is_array($td_data) && !empty($td_data['circledByCount'])) {
			//        $buffy_array = (int) $td_data['circledByCount'];
			//    }else{
			//        $td_data = td_remote_http::get_page("https://plus.google.com/$user_id/posts", __CLASS__);
			//        $pattern = "/<span role=\"button\" class=\"d-s o5a\" tabindex=\"0\">(.*?)<\/span>/";
			//        preg_match($pattern, $td_data, $matches);
			//        if (!empty($matches[1])) {
			//            $expl_maches = explode(' ', trim($matches[1]));
			//            $buffy_array = str_replace(array('.', ','), array(''), $expl_maches[0]);
			//        }
			//    }
			//    break;

            case 'instagram':
                $td_data = td_remote_http::get_page("https://instagram.com/$user_id#", __CLASS__);
                //$pattern = "/followed_by\":(.*?),\"follows\":/";
                //$pattern = "/followed_by\"\:\{\"count\"\:(.*?)\}\,\"/";

                // get the serialized data string present in the page script
                $pattern = '/window\._sharedData = (.*);<\/script>/';
                preg_match( $pattern, $td_data, $matches );

	            $instagram_followed_by_data = false;
	            if ( !empty( $matches[1] ) ) {
		            $instagram_data = json_decode( $matches[1], true );
		            if ( !empty( $instagram_data['entry_data']['ProfilePage'][0]["graphql"]['user']["edge_followed_by"]['count'] ) ) {
			            $instagram_followed_by_data = (int) $instagram_data['entry_data']['ProfilePage'][0]["graphql"]['user']["edge_followed_by"]['count'];
		            }
	            }

                if ( $instagram_followed_by_data ) {

                	// set followers data
                    $buffy_array = $instagram_followed_by_data;

                    // log success
                    td_log::log( __FILE__, __FUNCTION__, 'instagram "' . $user_id . '" page followers count data was retrieved successfully.', $buffy_array );

                } else {

	                // log no match found in page html data
	                td_log::log( __FILE__, __FUNCTION__, 'we haven\'t found a match in ' . $user_id . '\'s instagram page html data.', $td_data );

	                // try to get followers count using fb business connected account if available
                    $page_likes_number = $this->get_page_data_from_connected_fb_account( 'ig', $user_id );
                    if ( $page_likes_number !== false ) {
	                    $buffy_array = $page_likes_number;
                    }

                }

                break;

            case 'pinterest':
                $td_data = td_remote_http::get_page("https://pinterest.com/$user_id", __CLASS__);
                $pattern = "/followers\" content=([^>]+)/is";
                preg_match_all($pattern, $td_data, $matches);
                if (!empty($matches[1][0])) {
                    $buffy_array = $this->extract_numbers_from_string($matches[1][0]);
                }
                break;

            case 'tiktok':
                $td_data = td_remote_http::get_page("https://www.tiktok.com/$user_id?lang=en",  __CLASS__);
                $pattern = '/followerCount":(.*),/U';
                preg_match($pattern, $td_data, $matches);

                if ( !empty($matches) ) {
                    if ( !empty($matches[1]) ) {
                        $buffy_array = $this->extract_numbers_from_string($matches[1]);
                    }
                } else {
                    $td_data = td_remote_http::get_page("https://www.tiktok.com/node/share/video/$user_id",  __CLASS__);
                    $pattern = '/followerCount":(.*),/U';
                    preg_match($pattern, $td_data, $matches);
                    if ( !empty($matches[1]) ) {
                        $buffy_array = $this->extract_numbers_from_string($matches[1]);
                    }

                }
                break;

            case 'soundcloud':
                $td_data = @$this->get_json("http://api.soundcloud.com/users/$user_id.json?client_id=97220fb34ad034b5d4b59b967fd1717e");
                if (is_array($td_data) && !empty($td_data['followers_count'])) {
                    $buffy_array = (int) $td_data['followers_count'];
                }
                break;

            case 'rss':
                $buffy_array = (int) $user_id;
                break;

            case 'twitch':

	            $this->debug( __FUNCTION__ . ' >> url: ' . $user_id );

				// twitch api user data request
                $twitch_users_result = $this->td_twitch_api_request('users', array( 'login' => $user_id ) );
				$twitch_user_id = null;
				if ( is_array( $twitch_users_result ) && isset( $twitch_users_result['data'] ) && is_array( $twitch_users_result['data'] ) ) {
					foreach ( $twitch_users_result['data'] as $user_data ) {
						if ( $user_data['login'] === $user_id ) {
							$twitch_user_id = $user_data['id']; // set user id
							break;
						}
					}
				}

				// if we have an id ...
				if ( $twitch_user_id ) {

					// twitch api user follows data request
					$twitch_user_follows_result = $this->td_twitch_api_request( 'users/follows', array( 'to_id' => $twitch_user_id, 'first' => 1 ) );

					if ( is_array( $twitch_user_follows_result ) && isset( $twitch_user_follows_result['data'] ) && isset( $twitch_user_follows_result['total'] ) ) {
						$buffy_array = (int) $twitch_user_follows_result['total'];
					}

				}

                break;

        }

        return $buffy_array;
    }


	/**
	 * retrieve the page likes count trough fb graph api for connected fb/ig business accounts
	 *
	 * @param $service - fb/ig ( facebook or instagram )
	 * @param $page_username - facebook or instagram business page username
	 * @return false|int - the page likes count / false on failure or if no fb account is connected or no pages are found for the connected fb account
	 *
	 */
	private function get_page_data_from_connected_fb_account( $service, $page_username ) {

		// td_options fb_connected_account
		$td_options_fb_connected_account = td_options::get_array('td_fb_connected_account');

		// page likes init
		$page_likes = null;

		// check for a connected fb business account
		if ( !empty($td_options_fb_connected_account) ) {

			// fb connected account pages data
			$td_fb_account_pages = !empty( $td_options_fb_connected_account['fb_account_pages_data'] ) ? $td_options_fb_connected_account['fb_account_pages_data'] : array();

			if ( !empty($td_fb_account_pages) && is_array($td_fb_account_pages) ) {

				foreach ( $td_fb_account_pages as $page_data ) {

					// if any of the username/page id or page access token are not set in page data go further
					if ( !isset($page_data['username']) || !isset($page_data['id']) || !isset($page_data['page_access_token']) ) {
						continue;
					}

					// check service type and look for a page username match
					if ( $service === 'fb' ) {
						if ( strtolower($page_username) !== strtolower($page_data['username']) )
							continue;
					} elseif ( $service === 'ig' ) {
						$ig_page_username = !empty( $page_data['instagram_business_account'] ) ? $page_data['instagram_business_account']['username'] : false;
						if ( !$ig_page_username || $ig_page_username !== strtolower($page_username) )
							continue;
					}

					// if we have a match do a request using the page access token to get the page likes data
					if ( $service === 'fb' ) {
						// fb request data
						$page_id = $page_data['id'];
						$count_field = 'fan_count';
					} elseif ( $service === 'ig' ) {

						// ig request data
						if ( isset( $page_data['instagram_business_account'] ) && !empty( $page_data['instagram_business_account'] ) ) {
							$page_id = $page_data['instagram_business_account']['id'];
							$count_field = 'followers_count';
						} else {
							td_log::log( __FILE__, __FUNCTION__, '"' . $service . ' - ' . $page_username . '" - missing instagram_business_account in page data.', array() );
							break;
						}

					}

					// get page data ( do request )
					$fb_page_likes_data_api_url = 'https://graph.facebook.com/' . $page_id . '?fields=' . $count_field . '&access_token=' . $page_data['page_access_token'];
					$result = wp_remote_get( $fb_page_likes_data_api_url, array( 'timeout' => 60, 'sslverify' => false ) );

					if ( !is_wp_error($result) ) {

						// process result
						$page_data = json_decode( $result['body'] );

						if ( isset( $page_data->$count_field ) ) {
							td_log::log( __FILE__, __FUNCTION__, '"' . $service . ' - ' . $page_username . '" - facebook graph api page likes data was retrieved successfully.', $page_data->$count_field );
							$page_likes = (int) $page_data->$count_field;
						} else {
							// log missing fan count data
							td_log::log( __FILE__, __FUNCTION__, '"' . $service . ' - ' . $page_username . '" - facebook graph api page likes data request - missing fan count data.', $page_data );
						}

					} else {
						// log error
						td_log::log( __FILE__, __FUNCTION__, '"' . $service . ' - ' . $page_username . '" - facebook graph api page likes data request - error.', $result );
					}

					// break foreach loop on first match
					break;

				}

			}

		}

		return $page_likes ?: false;

	}

	/**
	 * get data from twitch API
	 *
	 * @param string $url
	 * @param array $args
	 *
	 * @return array|mixed|null|object|string
	 */
	protected function td_twitch_api_request( $url = '', $args = array() ) {

		$api_url = 'https://api.twitch.tv/helix/';

		$tds_twitch_api_client_id = ( !empty ( td_util::get_option('tds_twitch_api_client_id' ) ) ) ? esc_html( td_util::get_option('tds_twitch_api_client_id' ) ) : '';
		$tds_twitch_api_client_secret = ( !empty ( td_util::get_option('tds_twitch_api_client_secret' ) ) ) ? esc_html( td_util::get_option('tds_twitch_api_client_secret' ) ) : '';

		if ( empty( $tds_twitch_api_client_id ) || empty( $tds_twitch_api_client_secret ) ) {
			$this->debug( array(
				'tds_twitch_api_client_id' => $tds_twitch_api_client_id,
				'tds_twitch_api_client_secret' => $tds_twitch_api_client_secret
			), __FUNCTION__ . ' >> client id/secret' );
			return null;
		}

		$token = $this->td_twitch_api_get_token();

		if ( false === $token )
			return null;

		if ( is_array( $args ) && sizeof( $args ) > 0 ) {

			$this->debug( $args, __FUNCTION__ . ' >> $args' );

			$query_args = array();

			foreach ( $args as $arg_key => $arg_value ) {

				if ( ! empty ( $arg_value ) ) {

					// Comma separated values must be converted to arrays
					if ( is_string( $arg_value ) && strpos( $arg_value, ',') !== false )
						$arg_value = explode(',', $arg_value);

					// Add query args
					$query_args[$arg_key] = $arg_value;
				}
			}

			if ( sizeof( $query_args ) > 0 ) {
				// Extended "http_build_query" in order to add multiple args with the same key
				$query = http_build_query( $query_args,null, '&' );;
				$query_string = preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', $query);
				$url .= '?' . $query_string;
			}
		}

		$this->debug( __FUNCTION__ . ' >> url: ' . $url );

		$headers = array(
			'Client-ID' => $tds_twitch_api_client_id,
			'Authorization' => 'Bearer ' . base64_decode( $token )
		);

		$response = wp_remote_get( $api_url . $url, array(
			'timeout' => 15,
			'headers' => $headers
		));

		// Check for error
		if ( is_wp_error( $response ) )
			return null;

		$result = wp_remote_retrieve_body( $response );

		// Check for error
		if ( is_wp_error( $result ) ) {
			return null;
		}

		$result = json_decode( $result, true );

		$this->debug( $result, __FUNCTION__ . ' >> $result' );

		return $result;
	}

	/**
	 * get twitch api OAuth token
	 *
	 * @return bool|string
	 */
	private function td_twitch_api_get_token() {

		$tds_twitch_api_client_id = ( !empty ( td_util::get_option('tds_twitch_api_client_id' ) ) ) ? esc_html( td_util::get_option('tds_twitch_api_client_id' ) ) : '';
		$tds_twitch_api_client_secret = ( !empty ( td_util::get_option('tds_twitch_api_client_secret' ) ) ) ? esc_html( td_util::get_option('tds_twitch_api_client_secret' ) ) : '';

		$token_url = 'https://id.twitch.tv/oauth2/token';
		$token = get_transient( 'td_twitch_token' );

		if ( false !== $token ) {
			return $token;
		}

		$args = [
			'client_id' => $tds_twitch_api_client_id,
			'client_secret' => $tds_twitch_api_client_secret,
			'grant_type' => 'client_credentials'
		];

		$headers = [
			'Content-Type' => 'application/json'
		];

		$response = wp_remote_post( $token_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $args ),
			'timeout' => 15
		]);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result, true );

		/**
		 * additionally we can validate token for debugging:
		 * curl -H "Authorization: OAuth <access_token>" https://id.twitch.tv/oauth2/validate
		 *
		 * response example:
		 * {"client_id":"1cphbefbx1lbtyfvzx3ja268226w7y","scopes":[],"expires_in":5445171}
		 */
		$this->debug( $result, __FUNCTION__ );

		if ( $result === false || ! isset( $result['access_token'] ) )
			return false;

		$token = base64_encode( $result['access_token'] );

		set_transient( 'td_twitch_token', $token, $result['expires_in'] - 30 );

		return $token;
	}

	/**
	 * twitch api verify client credentials
	 *
	 * @param $client_id
	 * @param $client_secret
	 * @param bool $delete_cache
	 *
	 * @return mixed
	 */
	public function td_twitch_api_verify_client_credentials( $delete_cache = false ) {

		if ( $delete_cache )
			delete_transient( 'td_twitch_token' );

		$result = $this->td_twitch_api_request( 'games/top' );

		return $result;
	}

	/**
	 * debug
	 * @param $args
	 * @param string $title
	 *
	 * @return void
	 */
	function debug( $args, $title = false ) {

		if ( !$this->debug || ( td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax() ) )
			return;

		if ( defined( 'WP_DEBUG') && true === WP_DEBUG ) {

            echo '<pre class="td-container">';
			if ( $title ) {
				echo '<h3>' . $title . '</h3>';
			}
            print_r($args);
            echo '</pre>';
		}

	}

	/**
	 * enable debug mode
	 */
	public function enable_debug_mode() {
		$this->debug = true;
	}

}