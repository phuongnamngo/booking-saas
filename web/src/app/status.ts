const labels: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  in_progress: 'Đang làm',
  completed: 'Hoàn tất',
  cancelled: 'Đã hủy',
  no_show: 'Vắng mặt',
}

const actions: Record<string, string> = {
  confirmed: 'Xác nhận',
  in_progress: 'Bắt đầu',
  completed: 'Hoàn tất',
  no_show: 'Báo vắng',
  cancelled: 'Hủy',
}

export function statusLabel(status: string): string {
  return labels[status] ?? status
}

export function statusAction(status: string): string {
  return actions[status] ?? status
}

export function isClosed(status: string): boolean {
  return status === 'cancelled' || status === 'completed' || status === 'no_show'
}
