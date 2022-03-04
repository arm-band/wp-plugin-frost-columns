<?php
/*
Plugin Name: Frost Columns
Description: 管理者画面のカテゴリー、タグ、タクソノミー編集ページのテーブルに非公開記事・予約記事も含んだ記事数を表示する列を追加するプラグイン
Version:     0.0.1
Author:      アルム＝バンド
License: MIT
*/

namespace FrostColumns;

/**
 * Frost Column
 *
 */
class FrostColumns
{
    protected $taxName;
    protected $postTypeName;
    protected $countIncludePrivateID;
    protected $countIncludePrivateLabel;

    /**
     * mb_strlen() での空文字列判定のラッパー関数
     *
     * @param string $str        : 入力文字列
     *
     * @return boolean            : 出力文字列
     */
    public function boolStrLen( $str )
    {
        return mb_strlen(
            $str,
            'UTF-8'
        ) > 0;
    }
    /**
     * コンストラクタ
     *
     */
    public function __construct()
    {
        if(
            mb_strpos(
                parse_url(
                    $_SERVER['REQUEST_URI'],
                    PHP_URL_PATH
                ),
                'edit-tags.php',
                0,
                'UTF-8'
            ) !== false
        ) {
            // edit-tags.php のみで動作
            $tax = esc_html(
                filter_input(
                    INPUT_GET,
                    'taxonomy',
                    FILTER_SANITIZE_SPECIAL_CHARS
                )
            );
            // タクソノミーの名前を取得
            $this->taxName = $this->boolStrLen( $tax )
                ? $tax
                : '';
            $posttype = esc_html(
                filter_input(
                    INPUT_GET,
                    'post_type',
                    FILTER_SANITIZE_SPECIAL_CHARS
                )
            );
            // 投稿タイプの名前を取得
            $this->postTypeName = $this->boolStrLen( $posttype )
                ? $posttype
                : '';
            // ラベル
            $this->countIncludePrivateID = 'count_include_private';
            $this->countIncludePrivateLabel = 'カウント(非公開・予約含)';
        }
    }
    /**
     * SQLを直接発行して公開済み・非公開・予約投稿の記事の投稿IDのみの一覧を取得、その数をリンクと共に出力する
     *
     * @param int $termID : タームID
     *
     */
    public function countArticlesBySQL( $termID )
    {
        // $wpdb 読み込み (global で宣言した変数のスコープの兼ね合いでここで読み込み宣言する)
        require_once( ABSPATH . '/wp-load.php');
        global $wpdb;

        // 各タームのオブジェクトを取得
        $termObj = get_term( $termID, $this->taxName );
        // 投稿のカテゴリー、タグはキー名がタクソノミー編集ページと投稿一覧ページで異なるので変換する
        $taxNameParam = $this->taxName;
        switch ( $this->taxName ) {
            case 'category':
                $taxNameParam = 'category_name';
                break;
            case 'post_tag':
                $taxNameParam = 'tag';
                break;
            default:
                $taxNameParam = $this->taxName;
                break;
        }
        // カスタム投稿タイプならばリンクのGETパラメータに投稿タイプの名前を付与
        $postTypeParam = $this->boolStrLen( $this->postTypeName )
            ? '&post_type=' . $this->postTypeName
            : '';
        // SQLのクエリで指定する投稿タイプの名前の文字列をセット
        $postTypeDBParam = $this->boolStrLen( $this->postTypeName )
            ? $this->postTypeName
            : 'post';
        // WP_Query では結果の投稿オブジェクトが大き過ぎてメモリを食い潰すので直接SQLを発行してIDのみ結果として取得して省力化する
        $the_query = "SELECT " . $wpdb->prefix . "posts.ID FROM " . $wpdb->prefix . "posts LEFT JOIN " . $wpdb->prefix . "term_relationships ON (" . $wpdb->prefix . "posts.ID = " . $wpdb->prefix . "term_relationships.object_id) WHERE 1=1 AND ( " . $wpdb->prefix . "term_relationships.term_taxonomy_id IN ( %d ) ) AND " . $wpdb->prefix . "posts.post_type = %s AND ((" . $wpdb->prefix . "posts.post_status = 'publish' OR " . $wpdb->prefix . "posts.post_status = 'future' OR " . $wpdb->prefix . "posts.post_status = 'private')) GROUP BY " . $wpdb->prefix . "posts.ID ORDER BY " . $wpdb->prefix . "posts.post_date DESC";
        $results = $wpdb->get_results(
            $wpdb->prepare(
                $the_query,
                $termID,
                $postTypeDBParam
            )
        );
        $cnt = count($results);

        echo <<<CNT

<a href="edit.php?{$taxNameParam}={$termObj->slug}{$postTypeParam}">{$cnt}</a>

CNT;

    }
    /**
     * 欄としての列の出力
     *
     * @param array $columns : 列
     *
     * @return array $columns : 列
     *
     */
    function addCountIncludePrivateColumns( $columns )
    {
        echo <<<STL
<style>
    .taxonomy-{$this->taxName} .manage-column.num \{width: 90px;\}
    .taxonomy-{$this->taxName} .manage-column.column-id \{width: 60px;\}
</style>

STL;

        $columns[$this->countIncludePrivateID] = $this->countIncludePrivateLabel;
        return $columns;
    }
    /**
     * 欄としての列の出力
     *
     * @param string $content     : 出力するコンテンツ
     * @param string $column_name : 列名
     * @param int    $term_id     : タームID
     *
     */
    function customCountIncludePrivateColumns( $content, $column_name, $term_id )
    {
        if ( $column_name == $this->countIncludePrivateID ) {
            $this->countArticlesBySQL( $term_id );
        }
    }
    /**
     * 初期処理。アクションフック・フィルターフックを発動させる
     *
     */
    public function initialize()
    {
        // 列の追加
        add_filter(
            'manage_edit-' . $this->taxName . '_columns',
            [
                $this,
                'addCountIncludePrivateColumns'
            ]
        );
        // 追加した列に実際の値を表示させる
        add_action(
            'manage_' . $this->taxName . '_custom_column',
            [
                $this,
                'customCountIncludePrivateColumns'
            ],
            10,
            3
        );
    }
}

// 処理
$wp_ab_frostcolumns = new FrostColumns();

if( is_admin() ) {
    // 管理者画面を表示している場合のみ実行
    $wp_ab_frostcolumns->initialize();
}
