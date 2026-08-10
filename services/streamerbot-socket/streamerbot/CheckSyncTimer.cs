using System;
using Newtonsoft.Json;

public class CPHInline
{
    private const int InternalClient = 0;
    private const int XogoriaClient = 1;
    private const int RetryDelaySeconds = 15;
    private const int InFlightTimeoutSeconds = 75;

    public bool Execute()
    {
        string pending = CPH.GetGlobalVar<string>("syncPending", true);
        if (!string.Equals(pending, "true", StringComparison.OrdinalIgnoreCase))
            return true;

        string nextSyncValue = CPH.GetGlobalVar<string>("nextSyncTime", true);
        DateTime nextSync;
        if (!DateTime.TryParse(nextSyncValue, out nextSync))
        {
            CPH.LogError("[Sync] nextSyncTime is invalid: " + nextSyncValue);
            ScheduleRetry();
            return true;
        }

        if (DateTime.Now < nextSync)
            return true;

        string inFlight = CPH.GetGlobalVar<string>("syncInFlight", true);
        if (string.Equals(inFlight, "true", StringComparison.OrdinalIgnoreCase))
        {
            string startedValue = CPH.GetGlobalVar<string>("activeSyncStartedAt", true);
            DateTime startedAt;
            if (DateTime.TryParse(startedValue, out startedAt) &&
                DateTime.UtcNow - startedAt.ToUniversalTime() < TimeSpan.FromSeconds(InFlightTimeoutSeconds))
            {
                return true;
            }

            CPH.LogWarn("[Sync] Previous WebSocket sync timed out; retrying.");
            CPH.SetGlobalVar("syncInFlight", "false", true);
        }

        if (!CPH.WebsocketIsConnected(InternalClient))
        {
            CPH.LogWarn("[Sync] Internal Streamer.bot WebSocket is disconnected.");
            ScheduleRetry();
            return true;
        }

        if (!CPH.WebsocketIsConnected(XogoriaClient))
        {
            CPH.LogWarn("[Sync] Xogoria WebSocket is disconnected.");
            ScheduleRetry();
            return true;
        }

        string requestId = Guid.NewGuid().ToString("N");

        CPH.SetGlobalVar("activeSyncRequestId", requestId, true);
        CPH.SetGlobalVar("activeSyncStartedAt", DateTime.UtcNow.ToString("o"), true);
        CPH.SetGlobalVar("activeSyncBasisTime", nextSyncValue, true);
        CPH.SetGlobalVar("syncInFlight", "true", true);

        var commandsRequest = new
        {
            request = "GetCommands",
            id = "xogoria-sync-" + requestId + "-commands"
        };

        var actionsRequest = new
        {
            request = "GetActions",
            id = "xogoria-sync-" + requestId + "-actions"
        };

        CPH.WebsocketSend(
            JsonConvert.SerializeObject(commandsRequest),
            InternalClient
        );

        CPH.WebsocketSend(
            JsonConvert.SerializeObject(actionsRequest),
            InternalClient
        );

        CPH.LogInfo("[Sync " + requestId + "] Requested commands and actions over WebSocket.");
        return true;
    }

    private void ScheduleRetry()
    {
        CPH.SetGlobalVar("nextSyncTime", DateTime.Now.AddSeconds(RetryDelaySeconds).ToString("o"), true);
        CPH.SetGlobalVar("syncPending", "true", true);
        CPH.SetGlobalVar("syncInFlight", "false", true);
    }
}
