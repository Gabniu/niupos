export type SetupNotification = {
  id: string;
  title: string;
  message: string;
  readAt: string | null;
};

type Props = {
  notifications: SetupNotification[];
  onMarkRead: (id: string) => void;
};

export function SetupNotificationsPanel({ notifications, onMarkRead }: Props) {
  if (notifications.length === 0) return null;

  const unreadCount = notifications.filter((notification) => notification.readAt === null).length;

  return (
    <div className="mt-5 rounded-xl border border-slate-200 bg-white p-5">
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Setup notifications</p>
        <span className="text-[11px] text-slate-400">{unreadCount} unread</span>
      </div>
      <div className="mt-3 space-y-2">
        {notifications.slice(0, 5).map((notification) => (
          <div key={notification.id} className="flex items-start justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
            <div>
              <p className="text-xs font-medium text-slate-800">{notification.title}</p>
              <p className="mt-0.5 text-[11px] leading-4 text-slate-500">{notification.message}</p>
            </div>
            {notification.readAt === null && (
              <button type="button" onClick={() => onMarkRead(notification.id)} className="shrink-0 text-[11px] font-medium text-slate-700 underline-offset-2 hover:underline">
                Mark read
              </button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
