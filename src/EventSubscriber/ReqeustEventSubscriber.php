<?php

/**
 * @file
 * Contains Drupal\rest_translation_util\EventSubscriber\RequestEventSubscriber.
 */

namespace Drupal\rest_translation_util\EventSubscriber;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Event Subscriber Transaltion.
 */
class ReqeustEventSubscriber implements EventSubscriberInterface {


  /**
   * Code that should be triggered on event specified
   */
  public function onRequest(RequestEvent $event) {

    // Only preload on json/api requests.
    if ($event->getRequest()->getRequestFormat() == 'json' && $event->getRequest()->getMethod() == 'PATCH') {
      $path_parts = explode('/', $event->getRequest()->getPathInfo());
      
      // Check if we have enough path parts and safely assign variables
      if (count($path_parts) < 3) {
        return; // Not enough parts to process
      }
      
      $language = $path_parts[1] ?? '';
      $bundle = $path_parts[2] ?? '';
      $path_part_3 = $path_parts[3] ?? '';
      $path_part_4 = $path_parts[4] ?? '';
      
      // Check if first parameter is actually a bundle type (no language provided)
      if ($language == 'node' || $language == 'taxonomy') {
        // Pattern: /node/123 or /taxonomy/term/123 (default language)
        // Shift everything: language becomes bundle, bundle becomes path_part_3, etc.
        $bundle = $language;
        $path_part_3 = $path_parts[2] ?? '';
        $path_part_4 = $path_parts[3] ?? '';
        $language = \Drupal::languageManager()->getDefaultLanguage()->getId();
      }
      
      // Validate we have the right number of parameters for each bundle type
      $valid_structure = false;
      if ($bundle == "node" && !empty($path_part_3)) {
        // Pattern: [lang/]node/ID (3 or 4 parts total including empty first part)
        $valid_structure = true;
      } else if ($bundle == "taxonomy" && $path_part_3 == "term" && !empty($path_part_4)) {
        // Pattern: [lang/]taxonomy/term/ID (4 or 5 parts total including empty first part)
        $valid_structure = true;
      }
      
      // Create translation only if we have valid structure and language param
      if ($valid_structure && !empty($language)) {

        // Need to load node and taxonomy term differently
        if ($bundle == "node") {
          $nid = $path_part_3;
          $node = Node::load($path_part_3);
          if ($node && !$node->hasTranslation($language)) {
            \Drupal::logger('rest_translation_util')->debug(
              "Node with ID @id has no '@lang' translation: create it!", [
                '@id' => $nid,
                '@lang' => $language,
            ]);
            $node->addTranslation($language, ['title' => $node->label()])->save();
          }
        } else if ($bundle == "taxonomy") {
          $tid = $path_part_4;
          $term = Term::load($tid);
          if ($term && !$term->hasTranslation($language)) {
            \Drupal::logger('rest_translation_util')->debug(
              "Term with ID @id has no '@lang' translation: create it!", [
                '@id' => $tid,
                '@lang' => $language,
            ]);
            $term->addTranslation($language, ['name' => $term->label()])->save();
          }
        }

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Set a high priority so it is executed before routing.
    $events[KernelEvents::REQUEST][] = ['onRequest', 1000];
    return $events;
  }

}

