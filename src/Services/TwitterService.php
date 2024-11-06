<?php
namespace XAutoPoster\Services;

use Abraham\TwitterOAuth\TwitterOAuth;

class TwitterService {
    private $connection;
    private $debug = true;
    
    public function __construct($apiKey, $apiSecret, $accessToken, $accessTokenSecret) {
        try {
            if (empty($apiKey) || empty($apiSecret) || empty($accessToken) || empty($accessTokenSecret)) {
                throw new \Exception('Twitter API credentials missing');
            }

            $this->connection = new TwitterOAuth(
                $apiKey,
                $apiSecret,
                $accessToken,
                $accessTokenSecret
            );
            
            // Set timeouts
            $this->connection->setTimeouts(30, 30);
            
            // Verify credentials using v1.1
            $this->verifyCredentials();
            
            // Switch to v2 for other operations
            $this->connection->setApiVersion('2');
        } catch (\Exception $e) {
            error_log('Twitter Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function verifyCredentials() {
        try {
            // Temporarily switch to v1.1 for verification
            $this->connection->setApiVersion('1.1');
            $result = $this->connection->get('account/verify_credentials');
            
            if ($this->debug) {
                error_log('Twitter Verification Response: ' . print_r($result, true));
            }
            
            if ($this->connection->getLastHttpCode() !== 200) {
                throw new \Exception('API verification failed: HTTP ' . $this->connection->getLastHttpCode());
            }
            
            if (isset($result->errors)) {
                throw new \Exception($result->errors[0]->message ?? 'Unknown API error');
            }
            
            // Switch back to v2
            $this->connection->setApiVersion('2');
            
            return true;
        } catch (\Exception $e) {
            error_log('Twitter API Verification Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function sharePost($post) {
        try {
            $title = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
            $permalink = get_permalink($post);
            $hashtags = $this->getPostHashtags($post);
            $content = $this->formatPostContent($title, $permalink, $hashtags);
            
            $mediaIds = [];
            if (has_post_thumbnail($post)) {
                $imageId = get_post_thumbnail_id($post);
                $imagePath = get_attached_file($imageId);
                if ($imagePath && file_exists($imagePath)) {
                    $mediaId = $this->uploadMedia($imagePath);
                    if ($mediaId) {
                        $mediaIds[] = $mediaId;
                    }
                }
            }

            // Switch to v2 for tweet creation
            $this->connection->setApiVersion('2');
            
            $params = ['text' => $content];
            if (!empty($mediaIds)) {
                $params['media'] = ['media_ids' => $mediaIds];
            }
            
            $result = $this->connection->post('tweets', $params, true);
            
            if ($this->debug) {
                error_log('Twitter Share Response: ' . print_r($result, true));
            }
            
            if ($this->connection->getLastHttpCode() !== 201) {
                throw new \Exception('Share failed: HTTP ' . $this->connection->getLastHttpCode());
            }
            
            if (!isset($result->data->id)) {
                throw new \Exception($result->errors[0]->message ?? 'Share failed');
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Twitter Share Error: ' . $e->getMessage());
            throw new \Exception('Share error: ' . $e->getMessage());
        }
    }
    
    private function getPostHashtags($post) {
        $hashtags = [];
        
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $hashtags[] = '#' . preg_replace('/\s+/', '', $category->name);
            }
        }
        
        $tags = get_the_tags($post->ID);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $hashtags[] = '#' . preg_replace('/\s+/', '', $tag->name);
            }
        }
        
        return array_unique($hashtags);
    }
    
    private function formatPostContent($title, $permalink, $hashtags) {
        $options = get_option('xautoposter_options', []);
        $template = isset($options['post_template']) ? 
            $options['post_template'] : '%title% %link% %hashtags%';
            
        $hashtagsStr = implode(' ', array_slice($hashtags, 0, 3));
        
        $content = str_replace(
            ['%title%', '%link%', '%hashtags%'],
            [$title, $permalink, $hashtagsStr],
            $template
        );
        
        return mb_substr($content, 0, 280);
    }
    
    private function uploadMedia($imagePath) {
        try {
            if (!file_exists($imagePath)) {
                throw new \Exception('Image not found: ' . $imagePath);
            }
            
            $fileSize = filesize($imagePath);
            if ($fileSize > 5242880) {
                throw new \Exception('Image size too large (max: 5MB)');
            }
            
            // Switch to v1.1 for media upload
            $this->connection->setApiVersion('1.1');
            $media = $this->connection->upload('media/upload', ['media' => $imagePath]);
            
            if (!isset($media->media_id_string)) {
                throw new \Exception('Media upload failed');
            }
            
            return $media->media_id_string;
        } catch (\Exception $e) {
            error_log('Twitter Media Upload Error: ' . $e->getMessage());
            return false;
        } finally {
            // Switch back to v2
            $this->connection->setApiVersion('2');
        }
    }

    public function getTweetMetrics($tweetId) {
        try {
            $result = $this->connection->get('tweets/' . $tweetId, [
                'tweet.fields' => 'public_metrics'
            ]);
            
            if ($this->connection->getLastHttpCode() !== 200) {
                throw new \Exception('Failed to get metrics: HTTP ' . $this->connection->getLastHttpCode());
            }
            
            return isset($result->data->public_metrics) ? $result->data->public_metrics : null;
        } catch (\Exception $e) {
            error_log('Twitter Metrics Error: ' . $e->getMessage());
            return null;
        }
    }
}