<?php

namespace undpaul\toggl2redmine\Config;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;

class YamlConfigLoader extends FileLoader {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function load(mixed $resource, ?string $type = NULL): mixed {
    $configValues = Yaml::parse(file_get_contents($resource));

    return $configValues;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function supports(mixed $resource, ?string $type = NULL): bool {
    return is_string($resource) && 'yml' === pathinfo(
        $resource,
        PATHINFO_EXTENSION
    );
  }

}
