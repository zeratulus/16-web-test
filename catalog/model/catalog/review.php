<?php
class ModelCatalogReview extends Model {
	public function addReview($product_id, $data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET 
		    author = '" . $this->db->escape($data['name']) . "', 
		    customer_id = '" . (int)$this->customer->getId() . "', 
		    product_id = '" . (int)$product_id . "', 
		    text = '" . $this->db->escape($data['text']) . "',
		    rating = '" . (int)$data['rating'] . "',
		    date_added = NOW(),
		    email = '" . $this->db->escape($data['email']) . "',
		    benefits = '" . $this->db->escape($data['benefits']) . "',
		    limitations = '" . $this->db->escape($data['limitations']) . "',
		    ip = '" . $this->db->escape($data['ip']) . "',
		    user_agent = '" . $this->db->escape($data['user_agent']) . "'
		    ");

		$review_id = $this->db->getLastId();

		//Add images
        if (isset($data['images']) && !empty($data['images'])) {
            foreach ($data['images'] as $image) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "review_image (review_id, image) VALUES ('{$review_id}', '{$this->db->escape($image)}');");
            }
        }

		//Add Youtube video
		if (isset($data['video_id']) && !empty($data['video_id'])) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "review_youtube (review_id, video_id) VALUES ('{$review_id}', '{$this->db->escape($data['video_id'])}');");
        }

		if (in_array('review', (array)$this->config->get('config_mail_alert'))) {
			$this->load->language('mail/review');
			$this->load->model('catalog/product');
			
			$product_info = $this->model_catalog_product->getProduct($product_id);

			$subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

			$message  = $this->language->get('text_waiting') . "\n";
			$message .= sprintf($this->language->get('text_product'), html_entity_decode($product_info['name'], ENT_QUOTES, 'UTF-8')) . "\n";
			$message .= sprintf($this->language->get('text_reviewer'), html_entity_decode($data['name'], ENT_QUOTES, 'UTF-8')) . "\n";
			$message .= sprintf($this->language->get('text_rating'), $data['rating']) . "\n";
			$message .= $this->language->get('text_review') . "\n";
			$message .= html_entity_decode($data['text'], ENT_QUOTES, 'UTF-8') . "\n\n";

			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($this->config->get('config_email'));
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject($subject);
			$mail->setText($message);
			$mail->send();

			// Send to additional alert emails
			$emails = explode(',', $this->config->get('config_mail_alert_email'));

			foreach ($emails as $email) {
				if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
					$mail->setTo($email);
					$mail->send();
				}
			}
		}

		return $review_id;
	}

    public function addImage($review_id, $file)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "review_image (review_id, image) VALUES ('{$review_id}', '{$this->db->escape($file)}');");
        return $this->db->getLastId();
	}

	public function getReviewsByProductId($product_id, $start = 0, $limit = 20, $sort = 'r.date_added', $sortDirection = 'DESC') {

	    $allowedSorts = [
	        'r.date_added',
            'r.rating',
            'r.ip'
        ];

	    if (!in_array($sort, $allowedSorts)) {
	        $sort = 'r.date_added';
        }

	    if (strtoupper($sortDirection) != 'DESC' || strtoupper($sortDirection) != 'ASC') {
            $sortDirection = 'DESC';
        }

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$query = $this->db->query("SELECT r.review_id, r.author, r.rating, r.text, p.product_id, pd.name, p.price, p.image, 
            r.date_added, r.email, r.benefits, r.limitations, r.ip FROM " . DB_PREFIX . "review r 
                LEFT JOIN " . DB_PREFIX . "product p ON (r.product_id = p.product_id) 
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) 
            WHERE p.product_id = '" . (int)$product_id . "' AND 
                p.date_available <= NOW() AND 
                p.status = '1' AND 
                r.status = '1' AND 
                pd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
                ORDER BY {$sort} {$sortDirection} LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

    public function getReviewVideos($review_id)
    {
        $sql = "SELECT * FROM ".DB_PREFIX."review_youtube WHERE review_id = " . (int)$review_id;
        $results = $this->db->query($sql);
        return $results->rows;
	}

    public function getReviewImages($review_id)
    {
        $sql = "SELECT * FROM ".DB_PREFIX."review_image WHERE review_id = " . (int)$review_id;
        $results = $this->db->query($sql);
        return $results->rows;
    }

	public function getTotalReviewsByProductId($product_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product p ON (r.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.product_id = '" . (int)$product_id . "' AND p.date_available <= NOW() AND p.status = '1' AND r.status = '1' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row['total'];
	}
}