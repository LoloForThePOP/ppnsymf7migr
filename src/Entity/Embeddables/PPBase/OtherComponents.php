<?php

namespace App\Entity\Embeddables\PPBase;

use Doctrine\ORM\Mapping as ORM;

/**
 * This embeddable handles other components that can describe a Project Presentation.
 * Exemples are websites associated with the project (including social networks); a faq (= collection of questions and answers), business cards, etc. 
 * The pattern is extensible so that component types migth be added in the future.
 */

#[ORM\Embeddable]
class OtherComponents
{

#[ORM\Column(type: 'json', nullable: true)]
private ?array $otherComponents = [];



    public function getOtherComponents(): ?array
    {
        return $this->otherComponents;
    }

    public function setOtherComponents(?array $otherComponents): self
    {
        $this->otherComponents = $otherComponents;
        return $this;
    }

  

    public function getOC($key)
    {
        if ($this->otherComponents !== null && array_key_exists($key, $this->otherComponents)) {
            return $this->otherComponents[$key];
        }

        return [];
    }

    
    public function getOCItem($component_type, $item_id)
    {
        if ($this->otherComponents !== null && array_key_exists($component_type, $this->otherComponents)) {

            foreach ($this->otherComponents[$component_type] as &$item) {

                if ($item['id']==$item_id) {
                    return $item;
                }

            }

            return null;

        }

        return null;
    }

    
   
    public function setOCItem($component_type, $item_id, $updatedItem)
    {
        if ($this->otherComponents !== null && array_key_exists($component_type, $this->otherComponents)) {

            foreach ($this->otherComponents[$component_type] as &$item) {

                if ($item['id']==$item_id) {
                    
                    $item = $updatedItem;
                    $item['updatedAt'] = new \DateTimeImmutable();
                    return true;
                }


            }

            return null;

        }

        return null;
    }

    public function addOtherComponentItem($component_type, $item)
    {
        if ($component_type!==null) {

            $item['id'] = uniqid();
            $item['createdAt'] = new \DateTimeImmutable();
            $item['position'] = count($this->getOC($component_type));
            $this->otherComponents[$component_type][] = $item;

            return $this;
        }
    }

    public function deleteOtherComponentItem($component_type, $id)
    {

        $i=0;
        foreach($this->otherComponents[$component_type] as $element) {
            //check the property of every element
            if($element['id']==$id){
                unset($this->otherComponents[$component_type][$i]);
            }
            $i++;
        }

        $this->otherComponents[$component_type] = array_values($this->otherComponents[$component_type]);
        
        return $this;
    }



    public function positionOtherComponentItem($component_type, $itemsPositions)
    {
        if ($component_type!==null) {

            foreach ($this->otherComponents[$component_type] as &$item) {

                $newPosition = array_search($item['id'], $itemsPositions, false);

                $item['position']=$newPosition;

            }

            //reordering items by position

            usort($this->otherComponents[$component_type], function ($item1, $item2) {
                return $item1['position'] <=> $item2['position'];
            });

            return $this;
        }

    }

}
