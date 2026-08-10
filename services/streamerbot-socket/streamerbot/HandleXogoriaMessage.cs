using System;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;

public class CPHInline
{
    private const int XogoriaClient = 1;
    private const int RetryDelaySeconds = 15;

    public bool Execute()
    {
        string raw;
        if (!CPH.TryGetArg("message", out raw) || string.IsNullOrWhiteSpace(raw))
            return true;

        try
        {
            JObject message = JObject.Parse(raw);
            string type = message.Value<string>("type") ?? "";

            switch (type)
            {
                case "auth_ok":
                    SendConnectionTest();
                    break;

                case "connection_test_ack":
                    CPH.LogInfo("[Xogoria Socket] Full connection test succeeded.");
                    break;

                case "sync_ack":
                    HandleSyncAcknowledgement(message);
                    break;

                case "event_ack":
                case "pong":
                    break;

                case "auth_error":
                case "protocol_error":
                    CPH.LogError("[Xogoria Socket] Server error: " + raw);
                    break;

                default:
                    CPH.LogWarn("[Xogoria Socket] Unknown message: " + raw);
                    break;
            }
        }
        catch (Exception ex)
        {
            CPH.LogError("[Xogoria Socket] Invalid message: " + ex.Message);
        }

        return true;
    }

    private void HandleSyncAcknowledgement(JObject message)
    {
        string requestId = message.Value<string>("request_id") ?? "";
        string activeRequestId = CPH.GetGlobalVar<string>("activeSyncRequestId", true) ?? "";

        if (!string.Equals(requestId, activeRequestId, StringComparison.OrdinalIgnoreCase))
        {
            CPH.LogWarn("[Sync] Ignored acknowledgement for an inactive request: " + requestId);
            return;
        }

        bool success = message.Value<bool?>("success") ?? false;
        CPH.SetGlobalVar("syncInFlight", "false", true);

        if (!success)
        {
            string error = message.Value<string>("error") ?? "Unknown server error.";
            CPH.LogError("[Sync " + requestId + "] Server rejected snapshot: " + error);
            ScheduleRetry();
            return;
        }

        string basisTime = CPH.GetGlobalVar<string>("activeSyncBasisTime", true) ?? "";
        string currentNextSync = CPH.GetGlobalVar<string>("nextSyncTime", true) ?? "";
        JObject capture = message["capture"] as JObject;
        string captureId = capture == null ? "unknown" : capture.Value<string>("id") ?? "unknown";

        ClearActiveRequest();

        if (string.Equals(basisTime, currentNextSync, StringComparison.Ordinal))
        {
            CPH.SetGlobalVar("nextSyncTime", "", true);
            CPH.SetGlobalVar("syncPending", "false", true);
            CPH.LogInfo("[Sync " + requestId + "] Snapshot stored as " + captureId + ".");
        }
        else
        {
            CPH.SetGlobalVar("syncPending", "true", true);
            CPH.LogInfo(
                "[Sync " + requestId + "] Snapshot stored as " + captureId +
                "; newer changes remain queued."
            );
        }
    }

    private void SendConnectionTest()
    {
        var test = new
        {
            type = "connection_test",
            request_id = Guid.NewGuid().ToString("N")
        };

        CPH.WebsocketSend(JsonConvert.SerializeObject(test), XogoriaClient);
    }

    private void ScheduleRetry()
    {
        DateTime retryAt = DateTime.Now.AddSeconds(RetryDelaySeconds);
        string currentValue = CPH.GetGlobalVar<string>("nextSyncTime", true);
        DateTime current;

        if (DateTime.TryParse(currentValue, out current) &&
            current > DateTime.Now &&
            current < retryAt)
        {
            retryAt = current;
        }

        ClearActiveRequest();
        CPH.SetGlobalVar("nextSyncTime", retryAt.ToString("o"), true);
        CPH.SetGlobalVar("syncPending", "true", true);
    }

    private void ClearActiveRequest()
    {
        CPH.SetGlobalVar("syncInFlight", "false", true);
        CPH.SetGlobalVar("activeSyncRequestId", "", true);
        CPH.SetGlobalVar("activeSyncStartedAt", "", true);
        CPH.SetGlobalVar("activeSyncBasisTime", "", true);
    }
}
