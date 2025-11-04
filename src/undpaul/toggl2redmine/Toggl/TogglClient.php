<?php

namespace undpaul\toggl2redmine\Toggl;

use Carbon\Carbon;
use Ixudra\Toggl\Exceptions\InvalidConfigurationException;
use Ixudra\Toggl\TogglService;
use undpaul\toggl2redmine\Exception\ApiLimitException;
use undpaul\toggl2redmine\Exception\ConnectionException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class TogglClient extends TogglService implements TogglClientInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getMe(bool $with_related_data = FALSE): \stdClass {
    $cache = new FilesystemAdapter();

    $request_data = [];

    $cache_key_items = [
      'togglClient::me',
      $this->apiToken,
      $this->baseUrl,
      $this->apiVersionUrl,
      $this->workspaceId,
    ];

    if ($with_related_data === TRUE) {
      $request_data['with_related_data'] = TRUE;
      $cache_key_items[] = 'with-related-data';
    }

    $cache_key = md5(implode('::', $cache_key_items));

    // Try to get data from cache.
    $cached = $cache->getItem($cache_key);
    if ($cached->isHit()) {
      $data = $cached->get();
    }
    else {
      $data = $this->sendGetMessage($this->baseUrl . $this->apiVersionUrl . '/me', $request_data);
      // Cache expires after 15min.
      $cached->expiresAfter(900);
      $cached->set($data);
      $cache->save($cached);
    }

    $this->handleError($data);

    return $data['content'];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function timeEntries(?Carbon $startDate = NULL, ?Carbon $endDate = NULL) {
    $request_data = array();
    if (!empty($startDate) || !empty($endDate)) {
      $request_data = [
        'start_date' => $startDate->toDateTimeLocalString() . 'Z',
        'end_date' => $endDate->toDateTimeLocalString() . 'Z',
      ];
    }

    $data = $this->sendGetMessage($this->baseUrl . $this->apiVersionUrl . '/me/time_entries', $request_data);

    return $data['content'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function prepareMessage(string $url, array $data = array()) {
    if (empty($this->workspaceId)) {
      throw new InvalidConfigurationException('Workspace ID is required. Please add a valid workspace ID in the configuration file or set it via the setWorkspace() method.');
    }

    if (empty($this->apiToken)) {
      throw new InvalidConfigurationException('API token is required. Please add a valid API token in the configuration file.');
    }

    $data['workspace_id'] = (int) $this->workspaceId;
    $data['user_agent'] = 'undpaul/toggl2redmine';

    return $this->getCurlService()
        ->to($url)
        ->withOption('USERPWD', $this->apiToken . ':api_token')
        ->withData($data)
        ->returnResponseArray()
        ->asJson();
  }

  /**
   * Handle errors when fetching data.
   *
   * @param array<string, mixed> $data
   *   Data returned by cURL client.
   */
  protected function handleError(array $data): void {
    if (is_null($data) || !array_key_exists('content', $data)) {
      throw new ConnectionException('Failed connecting to toggl. Please try again later.');
    }

    if (isset($data['status']) && ($data['status'] !== 200)) {
      if ($data['status'] === 402) {
        $message_default = 'You have reached your hourly API limit. Please try again later.';
        throw new ApiLimitException($data['error'] ?? $message_default);
      }
      else {
        throw new \Exception('Failed connecting to toggl. Status code ' . $data['status']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function userWorkspaces(): \stdClass {
    $cache = new FilesystemAdapter();

    $cache_key_items = [
      'togglClient::userWorkspaces',
      $this->apiToken,
      $this->baseUrl,
      $this->apiVersionUrl,
      $this->workspaceId,
    ];

    $cache_key = md5(implode('::', $cache_key_items));

    // Try to get data from cache.
    $cached = $cache->getItem($cache_key);
    if ($cached->isHit()) {
      $data = $cached->get();
    }
    else {
      $data = parent::userWorkspaces();
      // Cache expires after 15min.
      $cached->expiresAfter(900);
      $cached->set($data);
      $cache->save($cached);
    }

    $this->handleError($data);

    return $data['content'] ?? [];
  }

}
