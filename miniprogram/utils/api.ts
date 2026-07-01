import { clearToken, getToken, setToken } from './storage';

type ApiResponse<T> = {
  code: number;
  message: string;
  data: T;
};

const app = () => getApp<{ globalData: { apiBaseUrl: string; token: string } }>();

let redirectingToLogin = false;

function handleUnauthorized() {
  if (redirectingToLogin) {
    return;
  }
  redirectingToLogin = true;
  clearToken();
  app().globalData.token = '';
  wx.reLaunch({
    url: '/pages/login/login',
    complete: () => {
      setTimeout(() => {
        redirectingToLogin = false;
      }, 500);
    },
  });
}

function request<T>(method: 'GET' | 'POST', url: string, data: Record<string, any> = {}): Promise<T> {
  return new Promise((resolve, reject) => {
    wx.request({
      url: `${app().globalData.apiBaseUrl}${url}`,
      method,
      data,
      header: {
        Authorization: `Bearer ${getToken()}`,
      },
      success(response: any) {
        const body = response.data as ApiResponse<T>;
        if (body && body.code === 0) {
          resolve(body.data);
          return;
        }
        if (body && body.code === 1002) {
          handleUnauthorized();
        }
        reject(new Error(body?.message || '请求失败'));
      },
      fail() {
        reject(new Error('网络不可用，请稍后再试'));
      },
    });
  });
}

function upload<T>(url: string, filePath: string, name: string): Promise<T> {
  return new Promise((resolve, reject) => {
    wx.uploadFile({
      url: `${app().globalData.apiBaseUrl}${url}`,
      filePath,
      name,
      header: {
        Authorization: `Bearer ${getToken()}`,
      },
      success(response: any) {
        let body: ApiResponse<T> | null = null;
        try {
          body = JSON.parse(response.data);
        } catch (error) {
          reject(new Error('上传失败，请稍后再试'));
          return;
        }
        if (body && body.code === 0) {
          resolve(body.data);
          return;
        }
        if (body && body.code === 1002) {
          handleUnauthorized();
        }
        reject(new Error(body?.message || '上传失败'));
      },
      fail() {
        reject(new Error('网络不可用，请稍后再试'));
      },
    });
  });
}

export const api = {
  async login(code: string) {
    const data = await request<{ token: string; user: any }>('POST', '/api/wx/login', { code });
    setToken(data.token);
    app().globalData.token = data.token;
    redirectingToLogin = false;
    return data;
  },
  profile: () => request<any>('GET', '/api/user/profile'),
  saveProfile: (payload: { nickname: string; avatar?: string }) => request<any>('POST', '/api/user/profile/save', payload),
  uploadAvatar: (filePath: string) => upload<any>('/api/user/avatar/upload', filePath, 'avatar'),
  categories: () => request<{ expense: any[]; income: any[] }>('GET', '/api/category/list'),
  createCategory: (payload: { type: number; name: string }) => request<any>('POST', '/api/category/create', payload),
  homeSummary: (month: string) => request<any>('GET', '/api/home/summary', { month }),
  recordList: (month: string) => request<any>('GET', '/api/record/list', { month }),
  recordDetail: (id: number) => request<any>('GET', '/api/record/detail', { id }),
  createRecord: (payload: Record<string, any>) => request<any>('POST', '/api/record/create', payload),
  updateRecord: (payload: Record<string, any>) => request<any>('POST', '/api/record/update', payload),
  deleteRecord: (id: number) => request<any>('POST', '/api/record/delete', { id }),
  monthBudget: (month: string) => request<any>('GET', '/api/budget/month', { month }),
  saveBudget: (month: string, amount: string) => request<any>('POST', '/api/budget/save', { month, amount }),
};
