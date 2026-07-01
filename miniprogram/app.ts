import { getToken } from './utils/storage';
import { apiConfig } from './config';

App({
  globalData: {
    apiBaseUrl: apiConfig.baseUrl,
    token: '',
  },
  onLaunch() {
    this.globalData.token = getToken();
  },
});
