<?php

namespace App\Utils;

/**
 * 配置中心
 */
class ConfigHelper
{
    /**
     * @var string 公共配置
     */
    static $MALL_COMMON = 'mall_common';
    static $CONTENT     = 'content';
    static $GOODS       = 'goods';
    static $MARKET      = 'market';
    static $ORDER       = 'order';
    static $OPERATE     = 'operate';
    static $PAY         = 'pay';
    static $SITE        = 'site';
    static $USER        = 'user';
    static $AFTERSALE   = 'aftersale';

    private static function isValidModule(string $module): bool
    {
        return in_array($module, [
            self::$MALL_COMMON,
            self::$CONTENT,
            self::$GOODS,
            self::$MARKET,
            self::$ORDER,
            self::$OPERATE,
            self::$PAY,
            self::$SITE,
            self::$USER,
            self::$AFTERSALE,
        ]);
    }

    /**
     * @param string $module
     * @param string $key
     * @param $default
     * @return array|false|mixed|null
     * @throws \Exception 针对非生产环境，如果无效的模块会抛出异常
     */
    public static function config(string $module, string $key, $default = null)
    {
        if (!static::isValidModule($module)) {
            if (SiteHelper::isProd()) {
                return $default;
            } else {
                throw new \Exception("module '{$module}' not found");
            }
        }
        static $configs;
        if (!isset($configs[$module])) {
            $config_dir = empty(getenv('NACOS_CONFIG_DIR')) ? '/data/www/nacos-config' : getenv('NACOS_CONFIG_DIR');
            $filename   = $config_dir . DIRECTORY_SEPARATOR . $module . '.ini';
            if (file_exists($filename)) {
                $configs[$module] = parse_ini_file($filename, true, INI_SCANNER_RAW);
            } else {
                $configs[$module] = [];
            }
        }
        if (empty($key)) {
            return $configs[$module] ?? null;
        }
        return $configs[$module][$key] ?? $default;
    }

    /**
     * 获取gtmurl地址
     *
     * @return string
     */
    public static function getGtmUrl($siteCode): string
    {
        $siteCode = strtoupper($siteCode);
        $url = g_config(self::$SITE, "GTM_URL_{$siteCode}", '');
        if (empty($url)) {
            $hostArr = MyFunction::instance()->getDomainUrlMapBySite($siteCode);
            $baseUrl = (string)($hostArr['pc']['url'] ?? '');
            $url =  "{$baseUrl}/proxy-gtm/";
        }

        return $url;
    }

    /**
     * 获取管理后台订单商品导出时，配置哪些用户ID支持导出价格
     *
     * @return array
     */
    public static function getOrderGoodsExportShowPriceAtAdminIds(): array
    {
        $str = g_config(self::$OPERATE, 'ORDER_GOODS_EXPORT_SHOW_PRICE_ADMIN_IDS');
        if (!$str) {
            return [];
        }

        return explode(',', $str);
    }

    /**
     * 获取管理后台订单商品导出时，配置哪些用户组ID支持导出价格
     *
     * @return array
     */
    public static function getOrderGoodsExportShowPriceAtAdminGroupIds(): array
    {
        $str = g_config(self::$OPERATE, 'ORDER_GOODS_EXPORT_SHOW_PRICE_ADMIN_GROUP_IDS');
        if (!$str) {
            return [];
        }

        return explode(',', $str);
    }

    /**
     * 是否记录日志
     *
     * @return bool
     */
    public static function isRecordLog(): bool
    {
        $value = g_config(self::$MALL_COMMON, 'IS_RECORD_LOG', 1);

        return intval($value) > 0;
    }

    /**
     * 是否启动url重定向功能
     *
     * @return bool
     */
    public static function isUrlRedirect(): bool
    {
        $value = g_config(self::$SITE, 'IS_URL_REDIRECT', 1);

        return intval($value) > 0;
    }

    /**
     * 是否开启us站点的重定向
     * us.bm.com
     *
     * @return bool
     */
    public static function isOpenUsDomainUrlRedirect(): bool
    {
        $value = g_config(self::$SITE, 'IS_US_DOMAIN_URL_REDIRECT', 0);

        return intval($value) > 0;
    }

    /**
     * us站点需要重定向的地址(白名单）
     *
     * @return array
     */
    public static function usUrlNotRedirectList(): array
    {
        $value = g_config(self::$SITE, 'US_URL_NOT_REDIRECT_LIST');
        if ($value && is_string($value)) {
            $urls = json_decode($value, true);
            if ($urls && is_array($urls) && count($urls)) {
                return $urls;
            }
        }

        return [];
    }

    /**
     * 需要重定向的url
     *
     * @return array
     */
    public static function urlRedirectUrlList(): array
    {
        $value = g_config(self::$SITE, 'URL_REDIRECT_LIST');
        if ($value && is_string($value)) {
            $urls = json_decode($value, true);
            if ($urls && is_array($urls) && count($urls)) {
                return $urls;
            }
        }

        return [];
    }

    /**
     * 商城collect推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductCollectionRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_COLLECTION_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城Customers Also Viewed推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductCustomersAlsoViewedRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_CUSTOMERS_ALSO_VIEWED_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城Hottest Deals推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchHottestDealsRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_HOTTEST_DEALS_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城Recommend For You推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchRecommendForYouRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_RECOMMEND_FOR_YOU_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城搜索热词推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchHotKeywordsRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_HOT_KEYWORDS_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城推荐是否切换为用增接口
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductClusterRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_CLUSTER_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商品详情底部内链推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductDetailRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_DETAIL_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 创作中心推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchCreationArticleRecommendForYouRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_CREATION_ARTICLE_RECOMMEND_FOR_YOU_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 新品推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchNewProductRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_NEW_PRODUCT_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 分类Trending Search推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchChannelAssociateWordRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_CHANNEL_ASSOCIATE_WORD_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * Frequently Bought Together推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductFrequentlyBoughtTogetherRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_FREQUENTLY_BOUGHT_TOGETHER_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * Frequently Bought Together推荐-默认
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductFrequentlyBoughtTogetherRecommendDefault(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_FREQUENTLY_BOUGHT_TOGETHER_RECOMMEND_DEFAULT_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商详页similar product推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductSimilarRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_SIMILAR_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * Choose Your Perfect Match推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductChooseYourPerfectMatchRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_CHOOSE_YOUR_PERFECT_MATCH_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商详页similar product V2推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductSimilarRecommendV2(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_SIMILAR_RECOMMEND_V2_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * Similar Items  in stock推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchProductSimilarInStockRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_PRODUCT_SIMILAR_IN_STOCK_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * You May Also Like
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchYouMayAlsoLikeRecommend(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_YOU_MAY_ALSO_LIKE_RECOMMEND_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 商城搜索热词推荐是否切换为用增搜索
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchHotKeywordsSearch(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_HOT_KEYWORDS_SEARCH_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 分类Trending Search推荐
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchChannelAssociateWordSearch(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_CHANNEL_ASSOCIATE_WORD_SEARCH_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 主搜
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchSearch(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_SEARCH_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 搜索侧边栏-主搜
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchSearchAttr(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_SEARCH_ATTR_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 搜索侧边栏-类目搜
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchCategorySearchAttr(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_CATEGORY_SEARCH_ATTR_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 分类搜索
     *
     * @param string $site
     * @return bool
     */
    public static function isSwitchCategorySearch(string $site): bool
    {
        $value = g_config(self::$GOODS, sprintf('IS_SWITCH_CATEGORY_SEARCH_%s', strtoupper($site)), 0);

        return intval($value) > 0;
    }

    /**
     * 虚拟规格属性开关
     *
     * @param string $spuId
     * @return bool
     */
    public static function virtualCloseStatus($spuId): bool
    {
        $value = g_config(self::$GOODS, 'VIRTUAL_SPU_CLOSE_CONFIG', "");
        if (!$value) {
            return false;
        }
        $closeSpuIds = explode(',', $value);
        return in_array($spuId, $closeSpuIds);
    }

    /**
     * 获取退货政策的文章ID
     *
     * @param string $site
     *
     * @return int
     */
    public static function getReturnExchangePolicyArticleId(string $site): int
    {
        return intval(g_config(ConfigHelper::$SITE, sprintf('RETURN_EXCHANGE_POLICY_ARTICLE_ID_%s', strtoupper($site)), 61));
    }

    /**
     * 获取Growth接口日志开关
     *
     * @return bool
     */
    public static function getGrowthLogStatus()
    {
        $value = g_config(self::$MALL_COMMON, 'GROWTH_LOG_STATUS', 0);
        return (int)$value > 0;
    }


    /**
     * 获取Growth接口缓存开关
     *
     * @return bool
     */
    public static function getGrowthCacheStatus()
    {
        $value = g_config(self::$MALL_COMMON, 'GROWTH_CACHE_STATUS', 1);
        return (int)$value > 0;
    }

    /**
     * 非美国国家是否开启地址校验，默认开启
     *
     * @return bool
     */
    public static function IsOpenUnUsAddressValidate(): bool
    {
        $value = g_config(self::$SITE, 'IS_OPEN_UN_US_ADDRESS_VALIDATE', 1);

        return intval($value) > 0;
    }



    /**
     * 获取订阅弹窗配置
     *
     * @return array
     */
    public static function getSubscribeDescriptionLinkConfig(): array
    {
        $result = g_config(ConfigHelper::$MALL_COMMON, 'SUBSCRIBE_DESCRIPTION_LINK');
        $result = json_decode($result, true);
        if (!$result || !is_array($result)) {
            return [];
        }
        return $result;
    }


    /**
     * 获取渠道列表
     *
     * @return array
     */
    public static function getSubscribeTrafficChannelConfig(): array
    {
        $result = g_config(ConfigHelper::$MALL_COMMON, 'SUBSCRIBE_TRAFFIC_CHANNEL');
        $result = json_decode($result, true);
        if (!$result || !is_array($result)) {
            return [];
        }
        return $result;
    }

    /**
     * 订阅弹窗：全量默认版本中允许编辑/启停的全局默认版本 ID 白名单（英文逗号分隔，如 1,2,5）
     *
     * @return list<int>
     */
    public static function getSubscribeBaselineGlobalEditWhitelistConfig(): array
    {
        $str = trim((string) g_config(self::$MALL_COMMON, 'SUBSCRIBE_BASELINE_GLOBAL_EDIT_WHITELIST', ''));
        if ($str === '') {
            return [];
        }
        $ids = [];
        foreach (explode(',', $str) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * 订阅弹窗：被引用模版中允许编辑/启停/删除的模版 ID 白名单（英文逗号分隔，如 1,3,5）
     *
     * @return list<int>
     */
    public static function getSubscribeTemplateReferencedEditWhitelistConfig(): array
    {
        $str = trim((string) g_config(self::$MALL_COMMON, 'SUBSCRIBE_TEMPLATE_REFERENCED_EDIT_WHITELIST', ''));
        if ($str === '') {
            return [];
        }
        $ids = [];
        foreach (explode(',', $str) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * 订阅弹窗：纯文案 logo 模版固定背景图 URL（配置中心 mall_common / SUBSCRIBE_TEMPLATE_PURE_TEXT_LOGO_IMAGE_URL）
     */
    public static function getSubscribeTemplatePureTextLogoImageUrl(): string
    {
        $default = 'https://img5.su-cdn.com/mall/file/2026/06/22/960x1144_0e973e5bb55c6c3d40cd973a475c70cd.png';
        $str     = trim((string) g_config(self::$MALL_COMMON, 'SUBSCRIBE_TEMPLATE_PURE_TEXT_LOGO_IMAGE_URL', ''));

        return $str !== '' ? $str : $default;
    }


    /**
     * 商品导入翻译处理
     *
     * @return string
     */
    public static function productImportTranslateType(): string
    {
        $value = g_config(self::$SITE, 'PRODUCT_IMPORT_TRANSLATE_TYPE', 'google_translator');

        return $value;
    }


    /**
     * 获取Pingpong-klarna的额度
     *
     * @return array
     */
    public static function getPingPongKlarnaQuota($countryCode): array
    {
        $key        = 'PINGPONG_KLARNA_QUOTA_' . strtoupper($countryCode);
        $quotaRange = g_config(self::$PAY, $key, '30-5000');
        list($minAmount, $maxAmount) = explode('-', $quotaRange);
        return [$minAmount, $maxAmount];
    }
}
