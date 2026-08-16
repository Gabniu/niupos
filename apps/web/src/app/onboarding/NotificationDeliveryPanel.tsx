export type NotificationDelivery = {
  id: string;
  channel: string;
  status: string;
  attempts: number;
  blockedReason: string | null;
  title: string;
  message: string;
};

type Props = { deliveries: NotificationDelivery[]; onSend: (deliveryId: string) => void; sendingId: string | null };

export function NotificationDeliveryPanel({ deliveries, onSend, sendingId }: Props) {
  if (deliveries.length === 0) return null;

  return (
    <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
      <p className="text-xs font-medium uppercase tracking-[0.16em] text-amber-800">Message delivery status</p>
      <div className="mt-3 space-y-2">
        {deliveries.slice(0, 5).map((delivery) => (
          <div key={delivery.id} className="flex items-start justify-between gap-3 rounded-lg border border-amber-100 bg-white/70 p-3">
            <div>
              <p className="text-xs font-medium text-slate-800">{delivery.channel.toUpperCase()} · {delivery.title}</p>
              <p className="mt-0.5 text-[11px] leading-4 text-slate-500">{delivery.blockedReason ?? delivery.message}</p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              <span className="text-[11px] font-medium text-amber-800">{delivery.status === "blocked" ? "Waiting" : delivery.status}</span>
              {delivery.channel === "email" && delivery.status !== "sent" && <button type="button" onClick={() => onSend(delivery.id)} disabled={sendingId === delivery.id} className="rounded-md border border-amber-200 bg-white px-2 py-1 text-[11px] font-medium text-amber-900 transition hover:border-amber-400 disabled:cursor-wait disabled:opacity-60">{sendingId === delivery.id ? "Sending…" : "Send again"}</button>}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
