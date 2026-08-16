"use client";

import { useEffect } from "react";

export function ServiceWorkerRegistration() {
  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;

    let refreshing = false;
    const refreshAfterActivation = () => {
      // A tab can remain controlled by an older shell after a deployment.
      // Reload exactly once when the replacement worker claims this tab so
      // stale page layers/chunks cannot survive an update.
      if (refreshing) return;
      refreshing = true;
      window.location.reload();
    };

    navigator.serviceWorker.addEventListener(
      "controllerchange",
      refreshAfterActivation,
    );

    void navigator.serviceWorker
      .register("/sw.js", { scope: "/" })
      .then((registration) => registration.update())
      .catch(() => undefined);

    return () => {
      navigator.serviceWorker.removeEventListener(
        "controllerchange",
        refreshAfterActivation,
      );
    };
  }, []);

  return null;
}
