import { mountElement } from "../ui/mount";
import { sdk } from "../ui/sdk";
import { ContextBootstrap } from "./ContextBootstrap";

// Высота iframe подстраивается под содержимое: SDK следит за размером документа сам.
sdk.autoResizeIframe();

mountElement(<ContextBootstrap />);
