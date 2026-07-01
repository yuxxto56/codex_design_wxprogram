/**
 * 小程序接口环境配置。
 *
 * local: 微信开发者工具本地调试，配合“本地设置 -> 不校验合法域名”使用。
 * production: 体验版、正式版使用，必须填写微信后台已配置的 HTTPS 合法域名。
 */
type ApiEnv = 'local' | 'production';

const CURRENT_ENV: ApiEnv = 'production';

const API_BASE_URL_MAP: Record<ApiEnv, string> = {
  local: 'http://127.0.0.1:8010',
  production: 'https://ljyfk.comtree.cn/admin2',
};

export const apiConfig = {
  env: CURRENT_ENV,
  baseUrl: API_BASE_URL_MAP[CURRENT_ENV],
};
