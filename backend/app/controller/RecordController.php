<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\RecordLogic;
use Throwable;

/**
 * 账单记录 API 控制器。
 *
 * 负责收支记录的新增、修改、删除、列表和详情入口；具体业务规则在 RecordLogic。
 */
final class RecordController extends BaseController
{
    private RecordLogic $logic;

    public function __construct(?RecordLogic $logic = null)
    {
        $this->logic = $logic ?? new RecordLogic();
    }

    /**
     * 新增一条收入或支出记录。
     *
     * 从 POST body 逐个读取 type、category_id、amount、remark、happened_at。
     */
    public function create(): array
    {
        try {
            return $this->success($this->logic->create($this->currentUserId(), $this->recordPayload()));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 修改一条当前用户自己的记录。
     *
     * 从 POST body 逐个读取 id 和记录字段。
     */
    public function update(): array
    {
        try {
            return $this->success($this->logic->update($this->currentUserId(), $this->postInt('id'), $this->recordPayload()));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 删除一条当前用户自己的记录。
     *
     * 从 POST body 逐个读取 id。
     */
    public function delete(): array
    {
        try {
            return $this->success($this->logic->delete($this->currentUserId(), $this->postInt('id')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 按月份获取账单列表。
     *
     * 从 GET query 逐个读取 month，格式 YYYY-MM。
     */
    public function list(): array
    {
        try {
            return $this->success($this->logic->list($this->currentUserId(), $this->getRequiredString('month')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 获取一条账单详情。
     *
     * 从 GET query 逐个读取 id。
     */
    public function detail(): array
    {
        try {
            return $this->success($this->logic->detail($this->currentUserId(), $this->getInt('id')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 逐个读取 POST body 中的账单字段并组装 Logic 入参。
     *
     * @return array 标准账单 payload。
     */
    private function recordPayload(): array
    {
        return [
            'type' => $this->postInt('type'),
            'category_id' => $this->postInt('category_id'),
            'amount' => $this->postRequiredString('amount'),
            'remark' => $this->postOptionalString('remark'),
            'happened_at' => $this->postInt('happened_at', time()),
        ];
    }
}
