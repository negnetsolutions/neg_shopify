<?php

namespace Drupal\neg_shopify\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\neg_shopify\Settings;

/**
 * Webhook Controller.
 */
class WebhookController extends ControllerBase {

  /**
   * Handles incomming webhook.
   */
  public function handleIncomingWebhook() {
    $data = file_get_contents('php://input');
    $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];

    // Verify the hook.
    if ($this->verifyWebhook($data, $hmac_header) === FALSE) {
      Settings::log('Invalid Webhook Received: %data', [
        '%data' => $data,
      ], 'error');
      throw new NotFoundHttpException();
    }

    $hook = $_SERVER['HTTP_X_SHOPIFY_TOPIC'];
    $payload = json_decode($data, TRUE);

    Settings::log('Incoming Webhook %hook', [
      '%hook' => $hook,
    ], 'debug');

    // Add the hook to the queue.
    $this->addQueue($hook, $payload);

    // Everything is okay.
    return new Response('Okay', Response::HTTP_OK);
  }

  /**
   * Validates the hook.
   */
  protected function verifyWebhook($data, $hmac_header) {
    $config = Settings::config();
    $api_secret = $config->get('api_secret');

    $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $api_secret, TRUE));
    return hash_equals($hmac_header, $calculated_hmac);
  }

  /**
   * Adds a webhook call to the process queue.
   */
  protected function addQueue($hook, $payload) {
    $queue = Settings::webhookQueue();
    $worker = Settings::webhookQueueWorker();

    // Open the batch.
    $queue->createItem([
      'hook' => $hook,
      'payload' => $payload,
    ]);

    $lock = \Drupal::lock();
    if ($lock->acquire('neg_shopify_process_webhook')) {
      $item = $queue->claimItem();
      if ($item !== FALSE) {
        try {
          $worker->processItem($item->data);
          $queue->deleteItem($item);
        }
        catch (\Exception $e) {
          // If there was an Exception trown because of an error
          // Releases the item that the worker could not process.
          // Another worker can come and process it.
          Settings::log('Could not process webhook: %m', [
            '%m' => $e->getMessage(),
          ], 'error');
          $queue->releaseItem($item);
        }
      }

      $lock->release('neg_shopify_process_webhook');
    }

    return TRUE;
  }

}
