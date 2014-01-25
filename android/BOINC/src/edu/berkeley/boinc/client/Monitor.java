/*******************************************************************************
 * This file is part of BOINC.
 * http://boinc.berkeley.edu
 * Copyright (C) 2012 University of California
 * 
 * BOINC is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License
 * as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 * 
 * BOINC is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with BOINC.  If not, see <http://www.gnu.org/licenses/>.
 ******************************************************************************/
package edu.berkeley.boinc.client;

import edu.berkeley.boinc.utils.*;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.Locale;
import java.util.Timer;
import java.util.TimerTask;
import android.app.NotificationManager;
import android.app.Service;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Binder;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;
import edu.berkeley.boinc.AppPreferences;
import edu.berkeley.boinc.R;
import edu.berkeley.boinc.rpc.CcState;
import edu.berkeley.boinc.rpc.CcStatus;
import edu.berkeley.boinc.rpc.GlobalPreferences;
import edu.berkeley.boinc.rpc.ProjectInfo;
import edu.berkeley.boinc.rpc.Transfer;
import edu.berkeley.boinc.rpc.AcctMgrInfo;

/**
 * Main Service of BOINC on Android
 * - manages life-cycle of the BOINC Client.
 * - frequently polls the latest status of the client (e.g. running tasks, attached projects etc)
 * - reports device status (e.g. battery level, connected to charger etc) to the client
 * - holds singleton of client status data model and applications persistent preferences
 */
public class Monitor extends Service {
	
	private static ClientStatus clientStatus; //holds the status of the client as determined by the Monitor
	private static AppPreferences appPrefs; //hold the status of the app, controlled by AppPreferences
	
	public ClientInterfaceImplementation clientInterface = new ClientInterfaceImplementation(); //provides functions for interaction with client via rpc
	
	public static Boolean monitorActive = false;
	
	// XML defined variables, populated in onCreate
	private String fileNameClient; 
	private String fileNameCLI; 
	private String fileNameCABundle; 
	private String fileNameClientConfig; 
	private String fileNameGuiAuthentication; 
	private String fileNameAllProjectsList; 
	private String boincWorkingDir; 
	private Integer clientStatusInterval;
	private Integer deviceStatusIntervalScreenOff;
	
	private Timer updateTimer = new Timer(true); // schedules frequent client status update
	private TimerTask statusUpdateTask = new StatusUpdateTimerTask();
	private boolean updateBroadcastEnabled = true;
	private DeviceStatus deviceStatus = null;
	private Integer screenOffStatusOmitCounter = 0;
	
	// screen on/off updated by screenOnOffBroadcastReceiver
	private boolean screenOn = false;
	
// attributes and methods related to Android Service life-cycle
	/**
	 * Extension of Android's Binder class to return instance of this service
	 * allows components bound to this service, to access its functions and attributes
	 */
	public class LocalBinder extends Binder {
        public Monitor getService() {
            return Monitor.this;
        }
    }
    private final IBinder mBinder = new LocalBinder();

    @Override
    public IBinder onBind(Intent intent) {
    	if(Logging.DEBUG) Log.d(Logging.TAG,"Monitor onBind");
        return mBinder;
    }
	
	@Override
    public void onCreate() {
		if(Logging.ERROR) Log.d(Logging.TAG,"Monitor onCreate()");
		
		// populate attributes with XML resource values
		boincWorkingDir = getString(R.string.client_path); 
		fileNameClient = getString(R.string.client_name); 
		fileNameCLI = getString(R.string.client_cli); 
		fileNameCABundle = getString(R.string.client_cabundle); 
		fileNameClientConfig = getString(R.string.client_config); 
		fileNameGuiAuthentication = getString(R.string.auth_file_name); 
		fileNameAllProjectsList = getString(R.string.all_projects_list); 
		clientStatusInterval = getResources().getInteger(R.integer.status_update_interval_ms);
		deviceStatusIntervalScreenOff = getResources().getInteger(R.integer.device_status_update_screen_off_every_X_loop);
		
		// initialize singleton helper classes and provide application context
		clientStatus = new ClientStatus(this);
		getAppPrefs().readPrefs(this);
		
		// set current screen on/off status
		PowerManager pm = (PowerManager)
		getSystemService(Context.POWER_SERVICE);
		screenOn = pm.isScreenOn();
		
		// initialize DeviceStatus wrapper
		deviceStatus = new DeviceStatus(getApplicationContext(), getAppPrefs());
		
		// register screen on/off receiver
        IntentFilter onFilter = new IntentFilter (Intent.ACTION_SCREEN_ON); 
        IntentFilter offFilter = new IntentFilter (Intent.ACTION_SCREEN_OFF); 
        registerReceiver(screenOnOffReceiver, onFilter);
        registerReceiver(screenOnOffReceiver, offFilter);
		
        // register and start update task
        // using .scheduleAtFixedRate() can cause a series of bunched-up runs
        // when previous executions are delayed (e.g. during clientSetup() )
        updateTimer.schedule(statusUpdateTask, 0, clientStatusInterval);
	}
	
    @Override
    public void onDestroy() {
    	if(Logging.ERROR) Log.d(Logging.TAG,"Monitor onDestroy()");
    	
    	// remove screen on/off receiver
    	unregisterReceiver(screenOnOffReceiver);
    	
        // Cancel the persistent notification.
    	((NotificationManager)getSystemService(Service.NOTIFICATION_SERVICE)).cancel(getResources().getInteger(R.integer.autostart_notification_id));
        
    	updateBroadcastEnabled = false; // prevent broadcast from currently running update task
		updateTimer.cancel(); // cancel task
		
		 // release locks, if held.
		clientStatus.setWakeLock(false);
		clientStatus.setWifiLock(false);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {	
    	//this gets called after startService(intent) (either by BootReceiver or AndroidBOINCActivity, depending on the user's autostart configuration)
    	if(Logging.ERROR) Log.d(Logging.TAG, "Monitor onStartCommand()");
		/*
		 * START_STICKY causes service to stay in memory until stopSelf() is called, even if all
		 * Activities get destroyed by the system. Important for GUI keep-alive
		 * For detailed service documentation see
		 * http://android-developers.blogspot.com.au/2010/02/service-api-changes-starting-with.html
		 */
		return START_STICKY;
    }
// --end-- attributes and methods related to Android Service life-cycle
	
// singleton getter
	/**
	 * Retrieve singleton of ClientStatus.
	 * @return ClientStatus, represents the data model of the BOINC client's status
	 * @throws Exception if client status has not been initialized
	 */
	public static ClientStatus getClientStatus() throws Exception{ //singleton pattern
		if (clientStatus == null) {
			// client status needs application context, but context might not be available
			// in static code. functions have to deal with Exception!
			if(Logging.WARNING) Log.w(Logging.TAG,"getClientStatus: clientStatus not yet initialized");
			throw new Exception("clientStatus not initialized");
		}
		return clientStatus;
	}
	
	/**
	 * Retrieve singleton of AppPreferences.
	 * @return AppPreferences, interface to Android applications persistent key-value store
	 */
	public static AppPreferences getAppPrefs() { //singleton pattern
		if (appPrefs == null) {
			appPrefs = new AppPreferences();
		}
		return appPrefs;
	}
// --end-- singleton getter
	
// public methods for Activities
    /**
     * read BOINC's all_project_list.xml and filters output for device's BOINC platform
     * stores list of projects in ClienStatus class.
     * Content does not change during runtime, call only upon start.
     */
	public void readAndroidProjectsList() {
		// try to get current client status from monitor
		ClientStatus status;
		try{
			status  = Monitor.getClientStatus();
		} catch (Exception e){
			if(Logging.WARNING) Log.w(Logging.TAG,"Monitor.readAndroidProjectList: Could not load data, clientStatus not initialized.");
			return;
		}
		
		ArrayList<ProjectInfo> allProjects = clientInterface.getAllProjectsList();
		ArrayList<ProjectInfo> androidProjects = new ArrayList<ProjectInfo>();
		
		if(allProjects == null) return;
		
		String platform = getString(getBoincPlatform());
		if(Logging.DEBUG) Log.d(Logging.TAG, "readAndroidProjectsList for platform: " + platform);
		
		//filter projects that do not support Android
		for (ProjectInfo project: allProjects) {
			for(String supportedPlatform: project.platforms) {
				if(supportedPlatform.contains(platform) && !androidProjects.contains(project)) {
					androidProjects.add(project);
					break;
				}
			}
		}
		
		// set list in ClientStatus
		status.setSupportedProjects(androidProjects);
	}

    /**
     * Force refresh of client status data model, will fire Broadcast upon success.
     */
    public void forceRefresh() {
    	if(Logging.DEBUG) Log.d(Logging.TAG,"forceRefresh()");
    	try{
    		updateTimer.schedule(new StatusUpdateTimerTask(), 0);
    	} catch (Exception e){} // throws IllegalStateException if called after timer got cancelled, i.e. after manual shutdown
    }
    
    /**
     * Quit BOINC client.
     * Tries to quit BOINC client process gracefully, kills the process if no reaction within defined time frame 
     */
    public void quitClient() {
		// try to get current client status from monitor
		ClientStatus status = null;
		try{
			status  = Monitor.getClientStatus();
		} catch (Exception e){
			if(Logging.WARNING) Log.w(Logging.TAG,"Monitor.quitClient: Could not load data, clientStatus not initialized.");
			// do not return here, try to shut down without publishing status
		}
    	String processName = boincWorkingDir + fileNameClient;
    	
    	updateBroadcastEnabled = false; // prevent broadcast from currently running update task
		updateTimer.cancel(); // cancel task
    	// no scheduled RPCs anymore
    	
    	// set client status to SETUP_STATUS_CLOSING to adapt layout accordingly
		if(status!=null)status.setSetupStatus(ClientStatus.SETUP_STATUS_CLOSING,true);
    	
    	// try graceful shutdown via RPC
		clientInterface.quit();
    	
    	// there might be still other AsyncTasks executing RPCs
    	// close sockets in a synchronized way
		clientInterface.close();
    	// there are now no more RPCs...
    	
    	// graceful RPC shutdown waiting period...
    	Boolean success = false;
    	Integer attempts = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_rpc_check_attempts);
    	Integer sleepPeriod = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_rpc_check_rate_ms);
    	for(int x = 0; x < attempts; x++) {
    		try {
    			Thread.sleep(sleepPeriod);
    		} catch (Exception e) {}
    		if(getPidForProcessName(processName) == null) { //client is now closed
        		if(Logging.DEBUG) Log.d(Logging.TAG,"quitClient: gracefull RPC shutdown successful after " + x + " seconds");
    			success = true;
    			x = attempts;
    		}
    	}
    	
    	if(!success) {
    		// graceful RPC shutdown was not successful, try OS signals
        	quitProcessOsLevel(processName);
    	}
    	
    	// cancel notification
		ClientNotification.getInstance(getApplicationContext()).cancel();
    	
    	// set client status to SETUP_STATUS_CLOSED to adapt layout accordingly
		if(status!=null)status.setSetupStatus(ClientStatus.SETUP_STATUS_CLOSED,true);
		
		//stop service, triggers onDestroy
		stopSelf();
    }
	
	/**
	 * Determines BOINC platform name corresponding to device's cpu architecture (ARM, x86 or MIPS).
	 * Defaults to ARM
	 * @return ID of BOINC platform name string in resources
	 */
	public int getBoincPlatform() {
		int platformId = 0;
		String arch = System.getProperty("os.arch");    
		String normalizedArch = arch.substring(0, 4).toUpperCase(Locale.US);
		if(normalizedArch.contains("ARM")) platformId = R.string.boinc_platform_name_arm;
		else if (normalizedArch.contains("MIPS")) platformId = R.string.boinc_platform_name_mips;
	    else if (normalizedArch.contains("86")) platformId= R.string.boinc_platform_name_x86;
	    else {
	    	if(Logging.WARNING) Log.w(Logging.TAG,"could not map os.arch (" + arch + ") to platform, default to arm.");
	    	platformId = R.string.boinc_platform_name_arm;
	    }
	    
	    if(Logging.DEBUG) Log.d(Logging.TAG,"BOINC platform: " + getString(platformId) + " for os.arch: " + arch);
		return platformId;
	}
	
	/**
	 * Returns path to file in BOINC's working directory that contains GUI authentication key
	 * @return absolute path to file holding GUI authentication key
	 */
	public String getAuthFilePath(){
		return boincWorkingDir + fileNameGuiAuthentication;
	}
	
	/**
	 * Returns DeviceStatus class. Initializes it, if necessary.
	 * @return device status class. containing the current status data
	 */
	public DeviceStatus getDeviceStatus() {
		if(deviceStatus == null) {
			deviceStatus = new DeviceStatus(getApplicationContext(), getAppPrefs());
		}
		return deviceStatus;
	}
// --end-- public methods for Activities
    
// multi-threaded frequent information polling
	/**
	 * Task to frequently and asynchronously poll the client's status. Executed in different thread.
	 */
	private final class StatusUpdateTimerTask extends TimerTask {
		@Override
		public void run() {
			updateStatus();
		}
	}
	
	/**
	 * Reports current device status to client and reads current client status.
	 * Updates ClientStatus and fires Broadcast.
	 * Called frequently to poll current status.
	 */
    private void updateStatus(){
		// check whether RPC client connection is alive
		if(!clientInterface.connectionAlive()) {
			if(clientSetup()) { // start setup routine
				// interact with client only if connection established successfully
				reportDeviceStatus();
				readClientStatus(true); // read initial data
			}
		}
		
    	if(!screenOn && screenOffStatusOmitCounter < deviceStatusIntervalScreenOff) screenOffStatusOmitCounter++; // omit status reporting according to configuration
    	else {
    		// screen is on, or omit counter reached limit
    		if(clientInterface.connectionAlive()) {
    			reportDeviceStatus();
    			readClientStatus(false); // readClientStatus is also required when screen is off, otherwise no wakeLock acquisition.
    		}
    	}
    }
    
    /**
     * Reads client status via RPCs
     * Optimized to retrieve only subset of information (required to determine wakelock state) if screen is turned off
     * @param forceCompleteUpdate forces update of entire status information, regardless of screen status
     */
    private void readClientStatus(Boolean forceCompleteUpdate) {
    	try{
    		// read ccStatus and adjust wakelocks and service state independently of screen status
    		// wake locks and foreground enabled when Client is not suspended, therefore also during
    		// idle.
    		CcStatus status = clientInterface.getCcStatus();
    		// treat cpu throttling as if it was computing
    		Boolean computing = (status.task_suspend_reason == BOINCDefs.SUSPEND_NOT_SUSPENDED) || (status.task_suspend_reason == BOINCDefs.SUSPEND_REASON_CPU_THROTTLE);
    		if(Logging.VERBOSE) Log.d(Logging.TAG,"readClientStatus(): computation enabled: " + computing);
			Monitor.getClientStatus().setWifiLock(computing);
			Monitor.getClientStatus().setWakeLock(computing);
			ClientNotification.getInstance(getApplicationContext()).setForeground(computing, this);
    		
			// complete status read, depending on screen status
    		// screen off: only read computing status to adjust wakelock, do not send broadcast
    		// screen on: read complete status, set ClientStatus, send broadcast
			// forceCompleteUpdate: read complete status, independently of screen setting
	    	if(screenOn || forceCompleteUpdate) {
	    		// complete status read, with broadcast
				if(Logging.VERBOSE) Log.d(Logging.TAG, "readClientStatus(): screen on, get complete status");
				CcState state = clientInterface.getState();
				ArrayList<Transfer>  transfers = clientInterface.getFileTransfers();
				AcctMgrInfo acctMgrInfo = clientInterface.getAcctMgrInfo();
				
				if( (status != null) && (state != null) && (state.results != null) && (state.projects != null) && (transfers != null) && (state.host_info != null) && (acctMgrInfo != null)) {
					Monitor.getClientStatus().setClientStatus(status, state.results, state.projects, transfers, state.host_info, acctMgrInfo);
					// Update status bar notification
					ClientNotification.getInstance(getApplicationContext()).update();
				} else {
					String nullValues = "";
					try{
						if(state == null) nullValues += "state,";
						if(state.results == null) nullValues += "state.results,";
						if(state.projects == null) nullValues += "state.projects,";
						if(transfers == null) nullValues += "transfers,";
						if(state.host_info == null) nullValues += "state.host_info,";
						if(acctMgrInfo == null) nullValues += "acctMgrInfo,";
					} catch (NullPointerException e) {};
					if(Logging.ERROR) Log.e(Logging.TAG, "readClientStatus(): connection problem, null: " + nullValues);
				}
				
				// check whether monitor is still intended to update, if not, skip broadcast and exit...
				if(updateBroadcastEnabled) {
			        Intent clientStatus = new Intent();
			        clientStatus.setAction("edu.berkeley.boinc.clientstatus");
			        getApplicationContext().sendBroadcast(clientStatus);
				}
	    	} 
			
		}catch(Exception e) {
			if(Logging.ERROR) Log.e(Logging.TAG, "Monitor.readClientStatus excpetion: " + e.getMessage(),e);
		}
    }
    
    // reports current device status to the client via rpc
    // client uses data to enforce preferences, e.g. suspend on battery
    /**
     * Reports current device status to the client via RPC
     * BOINC client uses this data to enforce preferences, e.g. suspend battery but requires information only/best available through Java API calls.
     */
    private void reportDeviceStatus() {
		if(Logging.VERBOSE) Log.d(Logging.TAG, "reportDeviceStatus()");
    	try{
	    	// set devices status
			if(deviceStatus != null) { // make sure deviceStatus is initialized
				Boolean reportStatusSuccess = clientInterface.reportDeviceStatus(deviceStatus.update()); // transmit device status via rpc
				if(reportStatusSuccess) screenOffStatusOmitCounter = 0;
				else if(Logging.DEBUG) Log.d(Logging.TAG,"reporting device status returned false.");
			} else if(Logging.WARNING) Log.w(Logging.TAG,"reporting device status failed, wrapper not initialized.");
		}catch(Exception e) {
			if(Logging.ERROR) Log.e(Logging.TAG, "Monitor.reportDeviceStatus excpetion: " + e.getMessage());
		}
    }
// --end-- multi-threaded frequent information polling
	
// BOINC client installation and run-time management
    /**
     * installs client binaries(if changed) and other required files
     * executes client process
     * triggers initial reads (e.g. preferences, project list etc)
     * @return Boolean whether connection established successfully
     */
	private Boolean clientSetup() {
		if(Logging.DEBUG) Log.d(Logging.TAG,"Monitor.clientSetup()");
		
		// try to get current client status from monitor
		ClientStatus status;
		try{
			status  = Monitor.getClientStatus();
		} catch (Exception e){
			if(Logging.WARNING) Log.w(Logging.TAG,"Monitor.clientSetup: Could not load data, clientStatus not initialized.");
			return false;
		}
		
		status.setSetupStatus(ClientStatus.SETUP_STATUS_LAUNCHING,true);
		String clientProcessName = boincWorkingDir + fileNameClient;

		String md5AssetClient = computeMd5(fileNameClient, true);
		//if(Logging.DEBUG) Log.d(Logging.TAG, "Hash of client (Asset): '" + md5AssetClient + "'");

		String md5InstalledClient = computeMd5(clientProcessName, false);
		//if(Logging.DEBUG) Log.d(Logging.TAG, "Hash of client (File): '" + md5InstalledClient + "'");

		// If client hashes do not match, we need to install the one that is a part
		// of the package. Shutdown the currently running client if needed.
		//
		if (!md5InstalledClient.equals(md5AssetClient)) {
			if(Logging.DEBUG) Log.d(Logging.TAG,"Hashes of installed client does not match binary in assets - re-install.");
			
			// try graceful shutdown using RPC (faster)
			if (getPidForProcessName(clientProcessName) != null) {
				if(connectClient()) {
					clientInterface.quit();
		    		Integer attempts = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_rpc_check_attempts);
		    		Integer sleepPeriod = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_rpc_check_rate_ms);
		    		for(int x = 0; x < attempts; x++) {
		    			try {
		    				Thread.sleep(sleepPeriod);
		    			} catch (Exception e) {}
		    			if(getPidForProcessName(clientProcessName) == null) { //client is now closed
		        			if(Logging.DEBUG) Log.d(Logging.TAG,"quitClient: gracefull RPC shutdown successful after " + x + " seconds");
		    				x = attempts;
		    			}
		    		}
				}
			}
			
			// quit with OS signals
			if (getPidForProcessName(clientProcessName) != null) {
				quitProcessOsLevel(clientProcessName);
			}

			// at this point client is definitely not running. install new binary...
			if(!installClient()) {
	        	if(Logging.WARNING) Log.w(Logging.TAG, "BOINC client installation failed!");
	        	return false;
	        }
		}
		
		// Start the BOINC client if we need to.
		//
		Integer clientPid = getPidForProcessName(clientProcessName);
		if(clientPid == null) {
        	if(Logging.DEBUG) Log.d(Logging.TAG, "Starting the BOINC client");
			if (!runClient()) {
	        	if(Logging.DEBUG) Log.d(Logging.TAG, "BOINC client failed to start");
				return false;
			}
		}

		// Try to connect to executed Client in loop
		//
		Integer retryRate = getResources().getInteger(R.integer.monitor_setup_connection_retry_rate_ms);
		Integer retryAttempts = getResources().getInteger(R.integer.monitor_setup_connection_retry_attempts);
		Boolean connected = false;
		Integer counter = 0;
		while(!connected && (counter < retryAttempts)) {
			if(Logging.DEBUG) Log.d(Logging.TAG, "Attempting BOINC client connection...");
			connected = connectClient();
			counter++;

			try {
				Thread.sleep(retryRate);
			} catch (Exception e) {}
		}
		
		if(connected) { // connection established
			// make client read override settings from file
			clientInterface.readGlobalPrefsOverride();
			// read preferences for GUI to be able to display data
			GlobalPreferences clientPrefs = clientInterface.getGlobalPrefsWorkingStruct();
			status.setPrefs(clientPrefs);
			// read supported projects
			readAndroidProjectsList();
			// set Android model as hostinfo
			// should output something like "Samsung Galaxy SII - SDK:15 ABI:armeabi-v7a"
			String model = Build.MANUFACTURER + " " + Build.MODEL + " - SDK:" + Build.VERSION.SDK_INT + " ABI: " + Build.CPU_ABI;
			if(Logging.DEBUG) Log.d(Logging.TAG,"reporting hostinfo model name: " + model);
			clientInterface.setHostInfo(model);
		}
		
		if(connected) {
			if(Logging.DEBUG) Log.d(Logging.TAG, "setup completed successfully"); 
			status.setSetupStatus(ClientStatus.SETUP_STATUS_AVAILABLE,false);
		} else {
			if(Logging.DEBUG) Log.d(Logging.TAG, "onPostExecute - setup experienced an error"); 
			status.setSetupStatus(ClientStatus.SETUP_STATUS_ERROR,true);
		}
		
		return connected;
	}
	
	/**
	 * Executes BOINC client.
	 * Using Java Runtime exec method
	 * @return Boolean success
	 */
    private Boolean runClient() {
    	Boolean success = false;
    	try { 
    		String[] cmd = new String[2];
    		
    		cmd[0] = boincWorkingDir + fileNameClient;
    		cmd[1] = "--daemon";
    		
        	Runtime.getRuntime().exec(cmd, null, new File(boincWorkingDir));
        	success = true;
    	} catch (IOException e) {
    		if(Logging.DEBUG) Log.d(Logging.TAG, "Starting BOINC client failed with exception: " + e.getMessage());
    		if(Logging.ERROR) Log.e(Logging.TAG, "IOException", e);
    	}
    	return success;
    }

    /**
     * Establishes connection to client and handles initial authentication
     * @return Boolean success
     */
	private Boolean connectClient() {
		Boolean success = false;
		
        success = clientInterface.connect();
        if(!success) {
        	if(Logging.DEBUG) Log.d(Logging.TAG, "connection failed!");
        	return success;
        }
        
        //authorize
        success = clientInterface.authorizeGuiFromFile(boincWorkingDir + fileNameGuiAuthentication);
        if(!success) {
        	if(Logging.DEBUG) Log.d(Logging.TAG, "authorization failed!");
        }
        return success;
	}
	
	/**
	 * Installs required files from APK's asset directory to the applications' internal storage.
	 * File attributes override and executable are defined here
	 * @return Boolean success
	 */
    private Boolean installClient(){

		installFile(fileNameClient, true, true);
		installFile(fileNameCLI, true, true);
		installFile(fileNameCABundle, true, false);
		installFile(fileNameClientConfig, true, false);
		installFile(fileNameAllProjectsList, true, false);
    	
    	return true; 
    }
    
    /**
     * Copies given file from APK assets to internal storage.
     * @param file name of file as it appears in assets directory
     * @param override define override, if already present in internal storage
     * @param executable set executable flag of file in internal storage
     * @return Boolean success
     */
	private Boolean installFile(String file, Boolean override, Boolean executable) {
    	Boolean success = false;
    	byte[] b = new byte [1024];
		int count; 
		
		// If file is executable, cpu architecture has to be evaluated
		// and assets directory select accordingly
		String source = "";
		if(executable) source = getAssestsDirForCpuArchitecture() + file;
		else source = file;
		
		try {
			if(Logging.DEBUG) Log.d(Logging.TAG, "installing: " + source);
			
    		File target = new File(boincWorkingDir + file);
    		
    		// Check path and create it
    		File installDir = new File(boincWorkingDir);
    		if(!installDir.exists()) {
    			installDir.mkdir();
    			installDir.setWritable(true); 
    		}
    		
    		if(target.exists()) {
    			if(override) target.delete();
    			else {
    				if(Logging.DEBUG) Log.d(Logging.TAG,"skipped file, exists and ovverride is false");
    				return true;
    			}
    		}
    		
    		// Copy file from the asset manager to clientPath
    		InputStream asset = getApplicationContext().getAssets().open(source); 
    		OutputStream targetData = new FileOutputStream(target); 
    		while((count = asset.read(b)) != -1){ 
    			targetData.write(b, 0, count);
    		}
    		asset.close(); 
    		targetData.flush(); 
    		targetData.close();

    		success = true; //copy succeeded without exception
    		
    		// Set executable, if requested
    		Boolean isExecutable = false;
    		if(executable) {
    			target.setExecutable(executable);
    			isExecutable = target.canExecute();
    			success = isExecutable; // return false, if not executable
    		}

    		if(Logging.DEBUG) Log.d(Logging.TAG, "install of " + source + " successfull. executable: " + executable + "/" + isExecutable);
    		
    	} catch (IOException e) {  
    		if(Logging.ERROR) Log.e(Logging.TAG, "IOException: " + e.getMessage());
    		if(Logging.DEBUG) Log.d(Logging.TAG, "install of " + source + " failed.");
    	}
		
		return success;
	}
	
	/**
	 * Determines assets directory (contains BOINC client binaries) corresponding to device's cpu architecture (ARM, x86 or MIPS)
	 * @return name of assets directory for given platform, not an absolute path.
	 */
	private String getAssestsDirForCpuArchitecture() {
		String archAssetsDirectory="";
		switch(getBoincPlatform()) {
		case R.string.boinc_platform_name_arm:
			archAssetsDirectory = getString(R.string.assets_dir_arm);
			break;
		case R.string.boinc_platform_name_x86:
			archAssetsDirectory = getString(R.string.assets_dir_x86);
			break;
		case R.string.boinc_platform_name_mips:
			archAssetsDirectory = getString(R.string.assets_dir_mips);
			break;
		}
	    return archAssetsDirectory;
	}

    /**
     * Computes MD5 hash of requested file
     * @param fileName absolute path or name of file in assets directory, see inAssets parameter
     * @param inAssets if true, fileName is file name in assets directory, if not, absolute path
     * @return md5 hash of file
     */
    private String computeMd5(String fileName, Boolean inAssets) {
    	byte[] b = new byte [1024];
		int count; 
		
		try {
			MessageDigest md5 = MessageDigest.getInstance("MD5");

			InputStream fs = null;
			if(inAssets) fs = getApplicationContext().getAssets().open(getAssestsDirForCpuArchitecture() + fileName); 
			else fs = new FileInputStream(new File(fileName)); 
			
    		while((count = fs.read(b)) != -1){ 
    			md5.update(b, 0, count);
    		}
    		fs.close();

			byte[] md5hash = md5.digest();
			StringBuilder sb = new StringBuilder();
			for (int i = 0; i < md5hash.length; ++i) {
				sb.append(String.format("%02x", md5hash[i]));
			}
    		
    		return sb.toString();
    	} catch (IOException e) {  
    		if(Logging.ERROR) Log.e(Logging.TAG, "IOException: " + e.getMessage());
    	} catch (NoSuchAlgorithmException e) {
    		if(Logging.ERROR) Log.e(Logging.TAG, "NoSuchAlgorithmException: " + e.getMessage());
		}
		
		return "";
    }
    
	/**
	 * Determines ProcessID corresponding to given process name
	 * @param processName name of process, according to output of "ps"
	 * @return process id, according to output of "ps"
	 */
    private Integer getPidForProcessName(String processName) {
    	int count;
    	char[] buf = new char[1024];
    	StringBuffer sb = new StringBuffer();
    	
    	//run ps and read output
    	try {
	    	Process p = Runtime.getRuntime().exec("ps");
	    	p.waitFor();
	    	InputStreamReader isr = new InputStreamReader(p.getInputStream());
	    	while((count = isr.read(buf)) != -1)
	    	{
	    	    sb.append(buf, 0, count);
	    	}
    	} catch (Exception e) {
    		if(Logging.ERROR) Log.e(Logging.TAG, "Exception: " + e.getMessage());
    		return null;
    	}

    	String [] processLinesAr = sb.toString().split("\n");
    	if (processLinesAr.length < 2) {
    		if(Logging.ERROR) Log.e(Logging.TAG,"getPidForProcessName(): ps output has less than 2 lines, failure!");
    		return null;
    	}
    	
    	// figure out what index PID has
    	String [] headers = processLinesAr[0].split("[\\s]+");
    	Integer PidIndex = 1;
    	for (int x = 0; x < headers.length; x++) {
    		if(headers[x].equals("PID")) {
    			PidIndex = x;
    			continue;
    		}
    	}
		if(Logging.DEBUG) Log.d(Logging.TAG,"getPidForProcessName(): PID at index: " + PidIndex + " for output: " + processLinesAr[0]);
    	
		Integer pid = null;
    	for(int y = 1; y < processLinesAr.length; y++) {
    		Boolean found = false;
    	    String [] comps = processLinesAr[y].split("[\\s]+");
    	    for(String arg: comps) {
    	    	if(arg.equals(processName)) {
    	    		if(Logging.DEBUG) Log.d(Logging.TAG,"getPidForProcessName(): " + processName + " found in line: " + y);
    	    		found = true;
    	    	}
    	    }
    	    if(found) {
	    	    try{
	    	    	pid = Integer.parseInt(comps[PidIndex]);
	        	    if(Logging.DEBUG) Log.d(Logging.TAG,"getPidForProcessName(): pid: " + pid); 
	    	    }catch (NumberFormatException e) {if(Logging.ERROR) Log.e(Logging.TAG,"getPidForProcessName(): NumberFormatException for " + comps[PidIndex] + " at index: " + PidIndex);}
	    	    continue;
    	    }
    	}
    	// if not happen in ps output, not running?!
		if(pid == null) if(Logging.DEBUG) Log.d(Logging.TAG,"getPidForProcessName(): " + processName + " not found in ps output!");
    	
    	// Find required pid
    	return pid;
    }
    
    /**
     * Exits a process by sending it Linux SIGQUIT and SIGKILL signals
     * @param processName name of process to be killed, according to output of "ps"
     */
    private void quitProcessOsLevel(String processName) {
    	Integer clientPid = getPidForProcessName(processName);
    	
    	// client PID could not be read, client already ended / not yet started?
    	if (clientPid == null) {
    		if(Logging.DEBUG) Log.d(Logging.TAG, "quitProcessOsLevel could not find PID, already ended or not yet started?");
    		return;
    	}
    	
    	if(Logging.DEBUG) Log.d(Logging.TAG, "quitProcessOsLevel for " + processName + ", pid: " + clientPid);
    	
    	// Do not just kill the client on the first attempt.  That leaves dangling 
		// science applications running which causes repeated spawning of applications.
		// Neither the UI or client are happy and each are trying to recover from the
		// situation.  Instead send SIGQUIT and give the client time to clean up.
		//
    	android.os.Process.sendSignal(clientPid, android.os.Process.SIGNAL_QUIT);
    	
    	// Wait for the client to shutdown gracefully
    	Integer attempts = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_os_check_attempts);
    	Integer sleepPeriod = getApplicationContext().getResources().getInteger(R.integer.shutdown_graceful_os_check_rate_ms);
    	for(int x = 0; x < attempts; x++) {
			try {
				Thread.sleep(sleepPeriod);
			} catch (Exception e) {}
    		if(getPidForProcessName(processName) == null) { //client is now closed
        		if(Logging.DEBUG) Log.d(Logging.TAG,"quitClient: gracefull SIGQUIT shutdown successful after " + x + " seconds");
    			x = attempts;
    		}
    	}
    	
    	clientPid = getPidForProcessName(processName);
    	if(clientPid != null) {
    		// Process is still alive, send SIGKILL
    		if(Logging.WARNING) Log.w(Logging.TAG, "SIGQUIT failed. SIGKILL pid: " + clientPid);
    		android.os.Process.killProcess(clientPid);
    	}
    	
    	clientPid = getPidForProcessName(processName);
    	if(clientPid != null) {
    		if(Logging.WARNING) Log.w(Logging.TAG, "SIGKILL failed. still living pid: " + clientPid);
    	}
    }
// --end-- BOINC client installation and run-time management
	
// screen on/off receiver
	/**
	 * broadcast receiver to detect changes to screen on or off, used to adapt scheduling of StatusUpdateTimerTask
	 * e.g. avoid polling GUI status RPCs while screen is off in order to save battery
	 */
	BroadcastReceiver screenOnOffReceiver = new BroadcastReceiver() { 
		@Override 
        public void onReceive(Context context, Intent intent) { 
			String action = intent.getAction();
			if(action.equals(Intent.ACTION_SCREEN_OFF)) {
				screenOn = false;
				if(Logging.DEBUG) Log.d(Logging.TAG, "screenOnOffReceiver: screen turned off");
			}
			if(action.equals(Intent.ACTION_SCREEN_ON)) {
				screenOn = true;
				if(Logging.DEBUG) Log.d(Logging.TAG, "screenOnOffReceiver: screen turned on, force data refresh...");
				forceRefresh();
			}
        } 
	}; 
// --end-- screen on/off receiver
}
