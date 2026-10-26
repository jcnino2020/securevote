<?php
declare(strict_types=1);
/** Dependency-free, paginated aggregate report. Standard PDF fonts use WinAnsi. */
function sv_pdf_report(array $lines): string {
 $encoded=[];
 foreach($lines as $line){$text=iconv('UTF-8','Windows-1252//TRANSLIT',(string)$line);$text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/','',$text===false?'':$text);foreach(explode("\n",wordwrap($text,82,"\n",true)) as $part)$encoded[]=$part;}
 $pages=array_chunk($encoded?:['No data.'],43);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
 foreach($pages as $index=>$page){$pageId=5+$index*2;$streamId=$pageId+1;$kids[]="$pageId 0 R";
  $stream="0.11 0.16 0.37 rg\nBT /F2 18 Tf 48 788 Td (SecureVote | Election results) Tj ET\n0.2 0.25 0.4 RG 48 772 m 547 772 l S\n0 0 0 rg\nBT /F1 10 Tf 14 TL 48 748 Td\n";
  foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.='('.$safe.") Tj T*\n";}
  $stream.="ET\nBT /F1 9 Tf 48 40 Td (Aggregate report - Page ".($index+1).' of '.count($pages).") Tj ET\n";
  $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamId.' 0 R >>';$objects[$streamId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
 }
 $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$count=max(array_keys($objects))+1;$pdf.="xref\n0 $count\n0000000000 65535 f \n";for($id=1;$id<$count;$id++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$id]);$pdf.="trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";return $pdf;
}
