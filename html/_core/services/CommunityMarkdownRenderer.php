<?php

final class CommunityMarkdownRenderer {
    private int $token = 0;
    private array $tokens = [ ];
    private array $headingIds = [ ];

    public function render( string $source ): string {
        $this->token = 0;
        $this->tokens = [ ];
        $this->headingIds = [ ];
        $source = str_replace( [ "\r\n", "\r" ], "\n", $source );
        return $this->renderLines( explode( "\n", $source ) );
    }

    private function renderLines( array $lines, bool $preserveLineBreaks = false ): string {
        $html = [ ];
        $count = count( $lines );

        for ( $index = 0; $index < $count; ) {
            $line = rtrim( $lines[ $index ] );
            if ( trim( $line ) === "" ) {
                $index++;
                continue;
            }

            if ( preg_match( '/^:::(note|tip|warning|danger|card|center)(?:\s+(.+))?$/i', trim( $line ), $match ) ) {
                [ $body, $index ] = $this->collectContainer( $lines, $index + 1 );
                $type = strtolower( $match[ 1 ] );
                $title = trim( $match[ 2 ] ?? "" );
                $titleHtml = $title === "" ? "" : '<h3>' . $this->inline( $title ) . '</h3>';
                $sharedClasses = match ( $type ) {
                    "tip" => "uiAlert uiAlertSuccess ",
                    "danger" => "uiAlert uiAlertError ",
                    "card" => "uiPanel ",
                    default => "uiAlert ",
                };
                $html[] = '<aside class="' . $sharedClasses . 'communityBlock communityBlock--' . $type . '">' . $titleHtml . $this->renderLines( $body, $preserveLineBreaks ) . '</aside>';
                continue;
            }

            if ( preg_match( '/^:::cards(?:\s+(.+))?$/i', trim( $line ), $match ) ) {
                [ $body, $index ] = $this->collectContainer( $lines, $index + 1 );
                $title = trim( $match[ 1 ] ?? "" );
                $titleHtml = "";
                if ( $title !== "" ) {
                    $titleId = $this->headingId( $title );
                    $titleLabel = htmlspecialchars( $title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
                    $titleHtml = '<h2 class="communityGridTitle" id="' . $titleId . '">' . $this->inline( $title ) . '<a class="communityHeadingLink" href="#' . $titleId . '" aria-label="Link to ' . $titleLabel . '">#</a></h2>';
                }
                $groups = preg_split( '/^\s*\+\+\+\s*$/m', implode( "\n", $body ) ) ?: [ ];
                $cards = "";
                foreach ( $groups as $group ) {
                    if ( trim( $group ) !== "" ) {
                        $cards .= '<article class="uiPanel communityCard">' . $this->renderLines( explode( "\n", trim( $group ) ), true ) . '</article>';
                    }
                }
                $html[] = $titleHtml . '<div class="communityCardGrid">' . $cards . '</div>';
                continue;
            }

            if ( preg_match( '/^```\s*([a-z0-9_-]*)\s*$/i', trim( $line ), $match ) ) {
                $code = [ ];
                $index++;
                while ( $index < $count && !preg_match( '/^```\s*$/', trim( $lines[ $index ] ) ) ) {
                    $code[] = $lines[ $index++ ];
                }
                if ( $index < $count ) $index++;
                $language = $match[ 1 ] === "" ? "" : ' data-language="' . htmlspecialchars( $match[ 1 ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '"';
                $html[] = '<pre><code' . $language . '>' . htmlspecialchars( implode( "\n", $code ), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '</code></pre>';
                continue;
            }

            if ( preg_match( '/^(#{1,4})\s+(.+)$/', trim( $line ), $match ) ) {
                $level = strlen( $match[ 1 ] );
                $headingId = $this->headingId( $match[ 2 ] );
                $headingLabel = trim( preg_replace( '/[`*_~\[\]()#]/', "", $match[ 2 ] ) ?? $match[ 2 ] );
                $tocDescription = "";
                if ( $index + 1 < $count && preg_match( '/^\{toc:\s*(.*?)\}\s*$/i', trim( $lines[ $index + 1 ] ), $descriptionMatch ) ) {
                    $tocDescription = trim( $descriptionMatch[ 1 ] );
                    $index++;
                }
                $descriptionAttribute = $tocDescription === "" ? "" : ' data-toc-description="' . htmlspecialchars( $tocDescription, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '"';
                $html[] = "<h{$level} id=\"{$headingId}\"{$descriptionAttribute}>" . $this->inline( $match[ 2 ] ) . '<a class="communityHeadingLink" href="#' . $headingId . '" aria-label="Link to ' . htmlspecialchars( $headingLabel, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '">#</a>' . "</h{$level}>";
                $index++;
                continue;
            }

            if ( preg_match( '/^\s*(?:---+|\*\*\*+)\s*$/', $line ) ) {
                $html[] = '<hr>';
                $index++;
                continue;
            }

            if ( $index + 1 < $count && str_contains( $line, "|" ) && preg_match( '/^\s*\|?(?:\s*:?-{3,}:?\s*\|)+\s*:?-{3,}:?\s*\|?\s*$/', $lines[ $index + 1 ] ) ) {
                $headers = $this->tableCells( $line );
                $index += 2;
                $rows = [ ];
                while ( $index < $count && trim( $lines[ $index ] ) !== "" && str_contains( $lines[ $index ], "|" ) ) {
                    $rows[] = $this->tableCells( $lines[ $index++ ] );
                }
                $table = '<div class="communityTableWrap"><table class="uiTable"><thead><tr>';
                foreach ( $headers as $cell ) $table .= '<th>' . $this->inline( $cell ) . '</th>';
                $table .= '</tr></thead><tbody>';
                foreach ( $rows as $row ) {
                    $table .= '<tr>';
                    foreach ( $headers as $cellIndex => $_ ) $table .= '<td>' . $this->inline( $row[ $cellIndex ] ?? "" ) . '</td>';
                    $table .= '</tr>';
                }
                $html[] = $table . '</tbody></table></div>';
                continue;
            }

            if ( preg_match( '/^\s*([-*])\s+(.+)$/', $line ) ) {
                [ $items, $index ] = $this->collectList( $lines, $index, false );
                $html[] = '<ul>' . $items . '</ul>';
                continue;
            }
            if ( preg_match( '/^\s*\d+[.)]\s+(.+)$/', $line ) ) {
                [ $items, $index ] = $this->collectList( $lines, $index, true );
                $html[] = '<ol>' . $items . '</ol>';
                continue;
            }

            if ( preg_match( '/^\s*>\s?(.*)$/', $line ) ) {
                $quotes = [ ];
                while ( $index < $count && preg_match( '/^\s*>\s?(.*)$/', $lines[ $index ], $match ) ) {
                    $quotes[] = $match[ 1 ];
                    $index++;
                }
                $html[] = '<blockquote>' . $this->renderLines( $quotes, $preserveLineBreaks ) . '</blockquote>';
                continue;
            }

            $paragraph = [ trim( $line ) ];
            $index++;
            while ( $index < $count && trim( $lines[ $index ] ) !== "" && !$this->startsBlock( $lines, $index ) ) {
                $paragraph[] = trim( $lines[ $index++ ] );
            }
            $separator = $preserveLineBreaks ? "<br>\n" : " ";
            $html[] = '<p>' . implode( $separator, array_map( fn( string $part ): string => $this->inline( $part ), $paragraph ) ) . '</p>';
        }
        return implode( "\n", $html );
    }

    private function collectContainer( array $lines, int $index ): array {
        $body = [ ];
        $count = count( $lines );
        $depth = 1;
        while ( $index < $count ) {
            $trimmed = trim( $lines[ $index ] );
            if ( preg_match( '/^:::(?:note|tip|warning|danger|card|center|cards)(?:\s|$)/i', $trimmed ) ) {
                $depth++;
                $body[] = $lines[ $index++ ];
                continue;
            }
            if ( $trimmed === ":::" ) {
                $depth--;
                $index++;
                if ( $depth === 0 ) break;
                $body[] = ":::";
                continue;
            }
            $body[] = $lines[ $index++ ];
        }
        return [ $body, $index ];
    }

    private function collectList( array $lines, int $index, bool $ordered ): array {
        $items = "";
        $pattern = $ordered ? '/^\s*\d+[.)]\s+(.+)$/' : '/^\s*[-*]\s+(.+)$/';
        while ( $index < count( $lines ) && preg_match( $pattern, $lines[ $index ], $match ) ) {
            $items .= '<li>' . $this->inline( $match[ 1 ] ) . '</li>';
            $index++;
        }
        return [ $items, $index ];
    }

    private function startsBlock( array $lines, int $index ): bool {
        $line = trim( $lines[ $index ] );
        if ( preg_match( '/^(?:#{1,4}\s|:::|```|>|[-*]\s|\d+[.)]\s|---+$|\*\*\*+$)/', $line ) ) return true;
        return $index + 1 < count( $lines ) && str_contains( $line, "|" ) && preg_match( '/^\s*\|?(?:\s*:?-{3,}:?\s*\|)+/', $lines[ $index + 1 ] );
    }

    private function tableCells( string $line ): array {
        return array_map( "trim", explode( "|", trim( trim( $line ), "|" ) ) );
    }

    private function inline( string $text ): string {
        $this->tokens = [ ];
        $text = preg_replace_callback( '/`([^`]+)`/', function( array $match ): string {
            return $this->stash( '<code>' . htmlspecialchars( $match[ 1 ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '</code>' );
        }, $text ) ?? $text;
        $text = preg_replace_callback( '/\[button:\s*([^\]]+)\]\(([^)]+)\)/i', function( array $match ): string {
            $url = $this->safeUrl( $match[ 2 ] );
            if ( $url === null ) return htmlspecialchars( $match[ 1 ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
            return $this->stash( '<a class="uiButton uiButtonPrimary communityButton" href="' . $url . '">' . htmlspecialchars( trim( $match[ 1 ] ), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '</a>' );
        }, $text ) ?? $text;
        $text = preg_replace_callback( '/\[([^\]]+)\]\(([^)]+)\)/', function( array $match ): string {
            $url = $this->safeUrl( $match[ 2 ] );
            if ( $url === null ) return htmlspecialchars( $match[ 1 ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
            $external = preg_match( '#^https?://#i', html_entity_decode( $url, ENT_QUOTES, "UTF-8" ) ) ? ' target="_blank" rel="noopener noreferrer"' : "";
            return $this->stash( '<a href="' . $url . '"' . $external . '>' . htmlspecialchars( $match[ 1 ], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" ) . '</a>' );
        }, $text ) ?? $text;

        $text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
        $text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text ) ?? $text;
        $text = preg_replace( '/~~(.+?)~~/s', '<del>$1</del>', $text ) ?? $text;
        $text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/s', '<em>$1</em>', $text ) ?? $text;
        foreach ( $this->tokens as $token => $html ) $text = str_replace( htmlspecialchars( $token, ENT_QUOTES, "UTF-8" ), $html, $text );
        return $text;
    }

    private function stash( string $html ): string {
        $token = "@@XOG" . $this->token++ . "@@";
        $this->tokens[ $token ] = $html;
        return $token;
    }

    private function safeUrl( string $url ): ?string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES, "UTF-8" ) );
        if ( preg_match( '#^(?:https?://|mailto:|/|\#)#i', $url ) !== 1 ) return null;
        return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
    }

    private function headingId( string $heading ): string {
        $plain = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $heading ) ?? $heading;
        $plain = strtolower( trim( preg_replace( '/[`*_~#]+/', "", $plain ) ?? $plain ) );
        $base = trim( preg_replace( '/[^\pL\pN]+/u', "-", $plain ) ?? "", "-" );
        if ( $base === "" ) $base = "section";
        $count = ( $this->headingIds[ $base ] ?? 0 ) + 1;
        $this->headingIds[ $base ] = $count;
        $id = $count === 1 ? $base : $base . "-" . $count;
        return htmlspecialchars( $id, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8" );
    }
}
