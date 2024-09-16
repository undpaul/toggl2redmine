<?php

namespace undpaul\toggl2redmine\Config;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class TimeEntrySyncConfiguration implements ConfigurationInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getConfigTreeBuilder(): TreeBuilder {
    $treeBuilder = new TreeBuilder('time-entry-sync');
    $rootNode = $treeBuilder->getRootNode();

    $rootNode
      ->children()
      ->scalarNode('redmineURL')
      ->end()
      ->scalarNode('redmineAPIKey')
      ->end()
      ->scalarNode('togglAPIKey')
      ->end()
      ->scalarNode('fromDate')
      ->end()
      ->scalarNode('toDate')
      ->end()
      ->scalarNode('defaultActivity')
      ->end()
      ->scalarNode('workspace')
      ->end()
      ->end()
    ;

    return $treeBuilder;
  }

}
