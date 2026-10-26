<?php
declare(strict_types=1);
/** One consistent header trail for public, account, voter, and admin pages. */
function sv_breadcrumbs(array $items): string {
 $html='<nav class="breadcrumb breadcrumb--header" aria-label="Breadcrumb navigation"><ol>';
 foreach($items as $index=>$item){
  $last=$index===count($items)-1;
  $html.='<li>';
  if(!$last && isset($item['href']))$html.='<a href="'.e(url($item['href'])).'">'.e(sv_t($item['label'])).'</a>';
  else $html.='<span aria-current="page">'.e(sv_t($item['label'])).'</span>';
  if(!$last)$html.='<span class="breadcrumb__separator" aria-hidden="true">'.sv_icon('chevron-right',14).'</span>';
  $html.='</li>';
 }
 return $html.'</ol></nav>';
}
