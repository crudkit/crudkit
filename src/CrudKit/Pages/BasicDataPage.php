<?php

namespace CrudKit\Pages;

use CrudKit\Data\BaseDataProvider;
use CrudKit\Util\FormHelper;
use CrudKit\Util\RouteGenerator;
use CrudKit\Util\TwigUtil;
use CrudKit\Util\ValueBag;
use CrudKit\Util\UrlHelper;
use CrudKit\Util\FlashBag;

class BasicDataPage extends BasePage{

    function render()
    {
        $twig = new TwigUtil();
        return $twig->renderTemplateToString("pages/basicdata.twig", array(
            'route' => new RouteGenerator(),
            'page' => $this,
            'name' => $this->name,
        ));
    }

    /**
     * Get the column specification and send to the client
     * @return array
     */
    public function handle_get_colSpec () {
        $url = new UrlHelper ();
        $filters = $url->get("filters_json", "[]");

        $params = array(
            'filters_json' => $filters
        );

        return array(
            'type' => 'json',
            'data' => array (
                'count' => $this->dataProvider->getRowCount($params),
                'schema' => $this->dataProvider->getSchema(),
                'columns' => $this->dataProvider->getSummaryColumns()
            )
        );
    }

    /**
     * Get Data
     * @return array
     */
    public function handle_get_data() {
        $url = new UrlHelper ();
        $pageNumber = $url->get('pageNumber', 1);
        $perPage = $url->get('perPage', 10);
        $filters = $url->get("filters_json", "[]");

        $params = array(
            'skip' => ($pageNumber - 1) * $perPage,
            'perPage' => ($pageNumber) * $perPage,
            'filters_json' => $filters
        );
        return array(
            'type' => 'json',
            'data' => array (
                'rows' => $this->dataProvider->getData($params)
            )
        );
    }

    public function handle_edit_item () {
        $twig = new TwigUtil();

        $url = new UrlHelper();
        $rowId = $url->get("item_id", null);
        $form = $this->dataProvider->getEditForm();
        $form->setPageId($this->getId());
        $form->setItemId($rowId);

        $route = new RouteGenerator();
        $deleteUrl = ($route->itemFunc($this->getId(), $rowId, "delete_item"));

        $formContent = $form->render($this->dataProvider->getEditFormOrder());
        $templateData = array(
            'page' => $this,
            'name' => $this->name,
            'editForm' => $formContent,
            'rowId' => $rowId,
            'canDelete' => true,
            'deleteUrl' => $deleteUrl
        );

        return $twig->renderTemplateToString("pages/basicdata/edit_item.twig", $templateData);
    }

    public function handle_delete_item () {
        $url = new UrlHelper();
        $rowId = $url->get("item_id", null);
        $route = new RouteGenerator();

        $status = $this->dataProvider->deleteItem ($rowId);
        FlashBag::add("alert", "Item has been deleted", "success");

        // Redirect back to the pageme
        return array(
            'type' => 'redirect',
            'url' => $route->openPage($this->getId())
        );
    }

    public function handle_new_item () {
        $twig = new TwigUtil();

        $form = $this->dataProvider->getEditForm();

        $form->setPageId($this->getId());
        $form->setNewItem();

        $formContent = $form->render($this->dataProvider->getEditFormOrder());
        $templateData = array(
            'page' => $this,
            'name' => $this->name,
            'editForm' => $formContent
        );

        return $twig->renderTemplateToString("pages/basicdata/edit_item.twig", $templateData);
    }

    public function handle_get_form_values () {
        $url = new UrlHelper ();
        $item_id = $url->get("item_id", null);
        if($item_id === "_ck_new"){
            return array(
                'type' => 'json',
                'data' => array (
                    'schema' => $this->dataProvider->getSchema(),
                    'values' => array()
                    )
                );
        }
        return array(
            'type' => 'json',
            'data' => array (
                'schema' => $this->dataProvider->getSchema(),
                'values' => $this->dataProvider->getRow($item_id)
            )
        );
    }

    public function handle_get_foreign () {
        $url = new UrlHelper();
        $foreign_key = $url->get("foreign_key", null);
        $item_id = $url->get("item_id", null);

        return array(
            'type' => 'json',
            'data' => array (
                'values' => $this->dataProvider->getRelationshipValues($item_id, $foreign_key)
            )
        );
    }

    public function handle_create_item () {
        $url = new UrlHelper();

        $values = json_decode($url->get("values_json", "{}"), true);

        //We have to check that all required fields are in request AND all provided data meet requirements
        $failedValues = array_merge(
            $this->dataProvider->validateRow($values), 
            $this->dataProvider->validateRequiredRow($values));
        if(empty($failedValues)){
            $new_pk = $this->dataProvider->createItem($values);
            FlashBag::add("alert", "Item has been created", "success");
            return array(
                'type' => 'json',
                'data' => array(
                   'success' => true,
                  'newItemId' => $new_pk
                )
            );
        }
        else {
            FlashBag::add("alert", "Could not set certain fields", "error");

            return array(
                'type' => 'json',
                'data' => array(
                    'success' => true,
                    'dataValid' => false,
                    'failedValues' => $failedValues
                )
            );
            //throw new \Exception("Cannot validate values");
        }
       
    }

    public function handle_set_form_values () {
        $form = new FormHelper(array(), $this->dataProvider->getEditFormConfig());
        $url = new UrlHelper();

        $values = json_decode($url->get("values_json", "{}"), true);
        if(empty($values)) {
            return array(
                'type' => 'json',
                'data' => array(
                    'success' => true
                )
            );
        }

        //validate
        $failedValues = $this->dataProvider->validateRow($values);
        if(empty($failedValues)) {
            $this->dataProvider->setRow($url->get("item_id", null), $values);
            FlashBag::add("alert", "Item has been updated", "success");
            return array(
                'type' => 'json',
                'data' => array(
                    'success' => true,
                    'dataValid' => true
                )
            );
        }
        else {
            FlashBag::add("alert", "Could not update certain fields", "error");

            return array(
                'type' => 'json',
                'data' => array(
                    'success' => true,
                    'dataValid' => false,
                    'failedValues' => $failedValues
                )
            );
            //throw new \Exception("Cannot validate values");
        }
    }

    /**
     * @var BaseDataProvider
     */
    protected $dataProvider = null;

    /**
     * @return BaseDataProvider
     */
    public function getDataProvider()
    {
        return $this->dataProvider;
    }

    /**
     * @param BaseDataProvider $dataProvider
     */
    public function setDataProvider($dataProvider)
    {
        $this->dataProvider = $dataProvider;
        $this->dataProvider->setPage($this);
    }

    public function init () {
        parent::init();
        $this->dataProvider->init();
    }

}