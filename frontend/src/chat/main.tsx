import "../ui/prevent-focus-scroll";
import { ContextBootstrap } from "../iframe/ContextBootstrap";
import { mountElement } from "../ui/mount";
import { ChatPage } from "./ChatPage";
import type { ChatPageData } from "./page-data";

// Хост чатов высоту окна не подстраивает, autoResizeIframe здесь не вызываем: страница скроллится сама.
mountElement(<ContextBootstrap<ChatPageData> page="chat" render={(data) => <ChatPage data={data} />} />);
