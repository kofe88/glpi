<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2017 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
*/

/* Test for inc/rulesoftwarecategorycollection.class.php */

class RuleSoftwareCategoryCollectionTest extends DbTestCase {

   /**
    * @covers RuleSoftwareCategoryCollection::prepareInputDataForProcess
    */
   public function testPrepareInputDataForProcess() {
      $this->Login();

      $collection = new RuleSoftwareCategoryCollection();

      //Only process name
      $input = ['name' => 'Software'];
      $params = $collection->prepareInputDataForProcess([], $input);
      $this->assertEquals($input, $params);

      //Process name + comment
      $input = ['name' => 'Software', 'comment' => 'Comment'];
      $params = $collection->prepareInputDataForProcess([], $input);
      $this->assertEquals($input, $params);

      //Process also manufacturer
      $input = ['name'             => 'Software',
                'comment'          => 'Comment',
                'manufacturers_id' => 1];
      $params = $collection->prepareInputDataForProcess([], $input);
      $this->assertEquals($params['manufacturer'], 'My Manufacturer');
   }

   /**
    * @test
    */
   public function testNoRuleMatches() {
      $this->Login();

      $categoryCollection = new RuleSoftwareCategoryCollection();

      //Default rule is disabled : no rule should match
      $input = ['name'             => 'MySoft',
                'manufacturer'     => 'My Manufacturer',
                '_system_category' => 'dev'];
      $result = $categoryCollection->processAllRules(null, null, $input);
      $this->assertEquals($result, ["_no_rule_matches" => 1]);
   }

   /**
    * @test
    */
   public function testRuleMatchImport() {
      $this->Login();

      $categoryCollection = new RuleSoftwareCategoryCollection();
      $rule               = new Rule();

      //Default rule is disabled : no rule should match
      $input = ['name'             => 'MySoft',
                'manufacturer'     => 'My Manufacturer',
                '_system_category' => 'dev'];

      $rules = getAllDatasFromTable('glpi_rules',
                                    "`uuid`='500717c8-2bd6e957-53a12b5fd38869.86003425'");
      $this->assertEquals(1, count($rules));

      $myrule = current($rules);
      $rule->update(['id' => $myrule['id'], 'is_active' => 1]);

      //Force reload of the rules list
      $categoryCollection->RuleList->load = true;

      //Run the rules engine a second time with the rule enabled
      $result = $categoryCollection->processAllRules(null, null, $input);
      $this->assertEquals($result, ["_import_category" => 1,
                                    "_ruleid"          => $myrule['id']]);

      //Set default rule as disabled, as it was before
      $rule->update(['id' => $myrule['id'], 'is_active' => 0]);
   }

   /**
    * @test
    */
   public function testRuleSetCategory() {
      $this->Login();

      $categoryCollection = new RuleSoftwareCategoryCollection();

      //Default rule is disabled : no rule should match
      $input = ['name'             => 'MySoft',
                'manufacturer'     => 'My Manufacturer',
                '_system_category' => 'dev'];

      //Let's enable the rule
      $rule     = new Rule();
      $criteria = new RuleCriteria();
      $action   = new RuleAction();

      //Force reload of the rules list
      $categoryCollection->RuleList->load = true;

      //Create a software category
      $category      = new SoftwareCategory();
      $categories_id = $category->importExternal('Application');

      $rules_id = $rule->add(['name'        => 'Import name',
                              'is_active'   => 1,
                              'entities_id' => 0,
                              'sub_type'    => 'RuleSoftwareCategory',
                              'match'       => Rule::AND_MATCHING,
                              'condition'   => 0,
                              'description' => ''
                             ]);

      $criteria->add(['rules_id'  => $rules_id,
                      'criteria'  => 'name',
                      'condition' => Rule::PATTERN_IS,
                      'pattern'   => 'MySoft'
                     ]);

      $action->add(['rules_id'    => $rules_id,
                    'action_type' => 'assign',
                    'field'       => 'softwarecategories_id',
                    'value'       => $categories_id
                   ]);

      $this->assertEquals(2, countElementsInTable('glpi_rules', "`sub_type`='RuleSoftwareCategory'"));

      //Test that a software category can be assigned
      $result = $categoryCollection->processAllRules(null, null, $input);
      $this->assertEquals($result, ["softwarecategories_id" => $categories_id,
                                    "_ruleid"               => $rules_id]);

   }

   /**
    * @test
    */
   public function testRuleIgnoreImport() {
      $this->Login();

      $categoryCollection = new RuleSoftwareCategoryCollection();

      //Let's enable the rule
      $rule     = new Rule();
      $criteria = new RuleCriteria();
      $action   = new RuleAction();

      //Force reload of the rules list
      $categoryCollection->RuleList->load = true;

      $rules_id = $rule->add(['name'        => 'Ignore import',
                               'is_active'   => 1,
                               'entities_id' => 0,
                               'sub_type'    => 'RuleSoftwareCategory',
                               'match'       => Rule::AND_MATCHING,
                               'condition'   => 0,
                               'description' => ''
                               ]);

       $criteria->add(['rules_id'  => $rules_id,
                       'criteria'  => '_system_category',
                       'condition' => Rule::PATTERN_IS,
                       'pattern'   => 'dev'
                      ]);

       $action->add(['rules_id'    => $rules_id,
                     'action_type' => 'assign',
                     'field'       => '_ignore_import',
                     'value'       => '1'
                    ]);

      $this->assertEquals(2, countElementsInTable('glpi_rules',
                                                  "`sub_type`='RuleSoftwareCategory'"));
      $this->assertEquals(1, countElementsInTable('glpi_rules',
                                                  "`sub_type`='RuleSoftwareCategory'
                                                     AND `is_active`='1'"));

      //Force reload of the rules list
      $categoryCollection->RuleList->load = true;

      $input = ['name'             => 'fusioninventory-agent',
                'manufacturer'     => 'Teclib',
                '_system_category' => 'dev'];

      //Test that a software category can be ignored
      $result = $categoryCollection->processAllRules(null, null, $input);
      $this->assertEquals($result, ["_ignore_import" => 1,
                                    "_ruleid"        => $rules_id]);

   }

}
