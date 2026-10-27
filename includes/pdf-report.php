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

/** Draws the structured results report directly as a compact vector PDF. */
final class SvResultsPdf
{
    private const W = 595;
    private const H = 842;
    private array $pages = [];
    private string $stream = '';

    public function page(string $section, string $status): void
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
        }
        $this->stream = '';
        $this->rect(0, 0, self::W, self::H, '#ffffff');
        $this->rect(0, 0, self::W, 91, '#1c285e');
        $this->rect(0, 91, self::W, 4, '#aeb9ff');
        $this->logo(46, 10, 0.76);
        $this->text('Secure', 76, 31, 15, '#ffffff', true);
        $this->text('Vote', 127, 31, 15, '#bfc9ff', true);
        $this->text('ELECTION REPORT', 208, 29, 8.5, '#bfc9ff', true);
        $this->text($section, 46, 65, 23, '#ffffff', true);
        $this->rect(458, 25, 91, 25, '#34447e');
        $this->text(strtoupper($status), 468, 42, 9, '#ffffff', true);
    }

    public function rect(float $x, float $top, float $w, float $h, string $color): void
    {
        $this->stream .= $this->color($color, 'rg') . sprintf("%.2F %.2F %.2F %.2F re f\n", $x, self::H - $top - $h, $w, $h);
    }

    /** Vector version of the shield-and-check mark used by the site logo. */
    public function logo(float $x, float $top, float $scale): void
    {
        $this->stream .= "q\n" . sprintf(
            "%.3F 0 0 %.3F %.2F %.2F cm\n",
            $scale, -$scale, $x, self::H - $top
        );
        $this->stream .= $this->color('#bfc9ff', 'rg')
            . "16 2.75 m 5.5 6.5 l 5.5 15.6 l "
            . "5.5 22.2 9.9 27.5 16 29.25 c "
            . "22.1 27.5 26.5 22.2 26.5 15.6 c 26.5 6.5 l h f\n";
        $this->stream .= $this->color('#1c285e', 'RG')
            . "2.6 w 1 J 1 j 11.4 16.2 m 14.6 19.4 l 20.6 12.8 l S\nQ\n";
    }

    public function line(float $x1, float $top1, float $x2, float $top2, string $color = '#e1e5f0', float $width = 1): void
    {
        $this->stream .= $this->color($color, 'RG') . sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, self::H - $top1, $x2, self::H - $top2);
    }

    public function text(string $value, float $x, float $baselineTop, float $size = 10, string $color = '#25304d', bool $bold = false): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        $encoded = preg_replace('/[\x00-\x1F\x7F]/', '', $encoded === false ? '' : $encoded);
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        $this->stream .= $this->color($color, 'rg') . sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $bold ? 'FB' : 'FR', $size, $x, self::H - $baselineTop, $safe
        );
    }

    public function wrap(string $value, float $x, float $top, float $maxWidth, float $size = 10, string $color = '#25304d', bool $bold = false, float $leading = 15): float
    {
        $words = preg_split('/\s+/u', trim($value)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $next = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && mb_strwidth($next, 'UTF-8') * $size * 0.52 > $maxWidth) {
                $this->text($line, $x, $top, $size, $color, $bold);
                $top += $leading;
                $line = $word;
            } else {
                $line = $next;
            }
        }
        if ($line !== '') {
            $this->text($line, $x, $top, $size, $color, $bold);
            $top += $leading;
        }
        return $top;
    }

    public function footer(int $page, int $total, string $title): void
    {
        $this->line(46, 800, 549, 800);
        $this->text('SECUREVOTE  /  ' . $title, 46, 817, 8, '#68738f', true);
        $this->text(sprintf('PAGE %d / %d', $page, $total), 492, 817, 8, '#68738f', true);
    }

    public function render(string $title): string
    {
        if ($this->stream !== '') {
            $this->pages[] = $this->stream;
            $this->stream = '';
        }
        $total = count($this->pages);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($this->pages as $index => $content) {
            $pageId = 5 + $index * 2;
            $streamId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $footer = new self();
            $footer->footer($index + 1, $total, $title);
            $content .= $footer->stream;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /FR 3 0 R /FB 4 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
            $objects[$streamId] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        return $pdf . "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function color(string $hex, string $operator): string
    {
        $rgb = array_map(static fn (int $n): float => $n / 255, [
            hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)),
        ]);
        return sprintf("%.3F %.3F %.3F %s\n", $rgb[0], $rgb[1], $rgb[2], $operator);
    }
}

/** @param array{title:string,final:bool,demo:bool,generated:string,window:string,ballots:int,eligible:int,tally:array,outcomes:array} $report */
function sv_pdf_results_report(array $report): string
{
    $pdf = new SvResultsPdf();
    $status = $report['final'] ? 'Final totals' : 'Live totals';
    $pdf->page('Election results', $status);

    $top = $pdf->wrap($report['title'], 46, 130, 500, 21, '#1c285e', true, 26);
    $pdf->text('VOTING WINDOW', 46, $top + 6, 8, '#68738f', true);
    $top = $pdf->wrap($report['window'], 46, $top + 23, 500, 10, '#394463');
    $pdf->text('Generated ' . $report['generated'] . ' (Philippine time)', 46, $top + 3, 9, '#68738f');
    $top += 22;

    if ($report['demo']) {
        $pdf->rect(46, $top, 503, 48, '#edf0ff');
        $pdf->rect(46, $top, 4, 48, '#6a78ce');
        $pdf->text('DEMO DATA', 61, $top + 17, 9, '#3446a1', true);
        $pdf->text('Simulated ballots for testing. Not an official election outcome.', 61, $top + 34, 9, '#34416b');
        $top += 66;
    } else {
        $top += 12;
    }

    $metrics = [
        ['BALLOTS CAST', number_format($report['ballots'])],
        ['ASSIGNED VOTERS', number_format($report['eligible'])],
        ['TURNOUT', $report['eligible'] > 0 ? number_format($report['ballots'] / $report['eligible'] * 100, 1) . '%' : 'N/A'],
    ];
    foreach ($metrics as $i => [$label, $value]) {
        $x = 46 + $i * 172;
        $pdf->rect($x, $top, 159, 73, '#f3f5fa');
        $pdf->text($label, $x + 14, $top + 23, 8, '#68738f', true);
        $pdf->text($value, $x + 14, $top + 54, 23, '#1c285e', true);
    }
    $top += 104;
    $pdf->text('Outcome summary', 46, $top, 15, '#1c285e', true);
    $pdf->text($report['final'] ? 'Based on submitted ballots' : 'Current leaders; voting is still open', 46, $top + 18, 9, '#68738f');
    $top += 37;

    if ($report['outcomes'] === []) {
        $pdf->text('No outcomes are available for this election.', 46, $top + 16, 10, '#68738f');
    } else {
        foreach ($report['outcomes'] as $outcome) {
            if ($top > 735) {
                $pdf->page('Outcome summary', $status);
                $top = 123;
            }
            $pdf->line(46, $top, 549, $top);
            $pdf->text((string) $outcome['position'], 46, $top + 20, 11, '#1c285e', true);
            $pdf->text((string) $outcome['outcome'], 455, $top + 20, 9, '#3446a1', true);
            $top = $pdf->wrap((string) $outcome['detail'], 46, $top + 38, 490, 9.5, '#4b5570', false, 13) + 10;
        }
    }
    if ($top < 740) {
        $pdf->text('Aggregate totals only. Individual voter choices are never disclosed.', 46, 767, 8.5, '#68738f');
    }

    if ($report['tally'] !== []) {
        $pdf->page('Detailed tally', $status);
        $top = 127;
        $pdf->text('Candidate votes by position', 46, $top, 15, '#1c285e', true);
        $pdf->text('Counts include submitted ballots only.', 46, $top + 18, 9, '#68738f');
        $top += 43;
        foreach ($report['tally'] as $entry) {
            $rows = $entry['rows'];
            $height = 51 + count($rows) * 18;
            if ($top + $height > 785) {
                $pdf->page('Detailed tally', $status);
                $top = 122;
            }
            $pdf->rect(46, $top, 503, $height, '#f3f5fa');
            $pdf->rect(46, $top, 4, $height, '#aeb9ff');
            $pdf->text((string) $entry['position']['name'], 61, $top + 18, 11, '#1c285e', true);
            $pdf->text('Choices on ' . (int) $entry['ballots_with_choice'] . ' ballots', 61, $top + 33, 8.5, '#68738f');
            $pdf->text('VOTES', 432, $top + 33, 8, '#68738f', true);
            $pdf->text('SHARE', 492, $top + 33, 8, '#68738f', true);
            $pdf->line(61, $top + 40, 534, $top + 40);
            foreach ($rows as $index => $row) {
                $y = $top + 55 + $index * 18;
                $name = $row['first_name'] . ' ' . $row['last_name'];
                if (mb_strwidth($name, 'UTF-8') > 42) {
                    $name = mb_strimwidth($name, 0, 40, '...', 'UTF-8');
                }
                $pdf->text($name, 61, $y, 9.5, '#25304d', isset($entry['leading'][(int) $row['id']]));
                $pdf->text((string) $row['vote_count'], 439, $y, 9.5, '#25304d', true);
                $share = $entry['total_selections'] > 0 ? round((int) $row['vote_count'] / $entry['total_selections'] * 100) : 0;
                $pdf->text($share . '%', 494, $y, 9, '#4b5570');
            }
            $top += $height + 10;
        }
    }
    return $pdf->render('AGGREGATE RESULTS');
}
