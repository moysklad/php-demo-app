import type { ReactElement } from "react";
import { createRoot } from "react-dom/client";
import "./theme.css";

/**
 * Snackbar внутри iframe не используем (результат действия — Banner на странице), а Modal.Provider
 * здесь не ставим: он переносит через портал все, что в него обернуто, — оборачивайте им сам Modal.
 */
export function mountElement(page: ReactElement): void {
  const root = document.getElementById("root");

  if (!root) {
    throw new Error("Не найден контейнер #root");
  }

  createRoot(root).render(page);
}
