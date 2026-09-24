import type { LoyaltyConnectionState } from "./types";

/** Ответ POST loyalty/connect.php: сообщение и новое состояние подключения для вкладки. */
export type LoyaltyConnectPayload = {
  message?: string;
  loyalty?: LoyaltyConnectionState;
};

export async function readLoyaltyConnectResponse(response: Response): Promise<LoyaltyConnectPayload | string> {
  const body = await response.text();
  const contentType = response.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    try {
      return JSON.parse(body) as LoyaltyConnectPayload;
    } catch {
      return body;
    }
  }

  return body;
}
