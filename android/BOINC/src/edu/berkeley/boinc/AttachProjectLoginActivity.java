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

package edu.berkeley.boinc;

import edu.berkeley.boinc.utils.*;
import java.net.URL;
import edu.berkeley.boinc.client.Monitor;
import android.app.Service;
import android.content.ComponentName;
import android.content.Intent;
import android.content.ServiceConnection;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.AsyncTask;
import android.os.Bundle;
import android.os.IBinder;
import android.support.v7.app.ActionBar;
import android.support.v7.app.ActionBarActivity;
import android.text.InputType;
import android.text.SpannableString;
import android.text.style.UnderlineSpan;
import android.util.Log;
import android.view.View;
import android.view.View.OnClickListener;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import edu.berkeley.boinc.rpc.PlatformInfo;
import edu.berkeley.boinc.rpc.ProjectConfig;
import edu.berkeley.boinc.rpc.ProjectInfo;

public class AttachProjectLoginActivity extends ActionBarActivity{
	
	private Monitor monitor;
	private Boolean mIsBound = false;
	
	private String url = "";
	private ProjectInfo projectInfo;
	private Boolean projectInfoPresent = false; // complete ProjectInfo available, if selection from list
	private ProjectConfig projectConfig;
	private Bitmap projectLogo;
	private ActionBar actionBar;
	
	private ServiceConnection mConnection = new ServiceConnection() {
	    public void onServiceConnected(ComponentName className, IBinder service) {
	        // This is called when the connection with the service has been established, getService returns the Monitor object that is needed to call functions.
	        monitor = ((Monitor.LocalBinder)service).getService();
		    mIsBound = true;
		    
		    (new GetProjectConfig()).execute(url);
	    }

	    public void onServiceDisconnected(ComponentName className) { // This should not happen
	        monitor = null;
		    mIsBound = false;
	    }
	};
	
    @Override
    public void onCreate(Bundle savedInstanceState) {  
        super.onCreate(savedInstanceState);  

    	//parse master url from intent extras
        Boolean urlPresent = false;
        try {
        	url = getIntent().getCharSequenceExtra("url").toString();
        	if(Logging.DEBUG) Log.d(Logging.TAG,"AttachProjectLoginActivity onCreate with url: " + url);
        	if(url != null) urlPresent = true;
        } catch (Exception e) {}
        
        //parse  project info from intent extras
        try {
        	projectInfo = (ProjectInfo) getIntent().getSerializableExtra("projectInfo");
        	if(Logging.VERBOSE) Log.v(Logging.TAG,"projectInfo: " + projectInfo);
        	if(projectInfo != null) {
        		projectInfoPresent = true;
        		url = projectInfo.url; // set url field to information of projectInfo
        	}
        } catch (Exception e) {if(Logging.DEBUG) Log.d(Logging.TAG,"no project info...");}
        
        if(!projectInfoPresent) { // url can not be taken of ProjectInfo
        	// format user input on URL right to avoid exceptions
        	if (!url.startsWith("http://") && !url.startsWith("https://")) url = "http://" + url; // add http:// in case user leaves it out
        	if (!url.endsWith("/")) url = url + "/"; // add trailing slash
        	if(Logging.DEBUG) Log.d(Logging.TAG,"onCreate url sanitized to: " + url);
        }
        
        if(!urlPresent && !projectInfoPresent) {
        	// neither url (manual input) nor project info (list selection) is present
        	if(Logging.WARNING) Log.w(Logging.TAG,"neither url nor projectInfo available! finish activity...");
        	finish(R.string.attachproject_login_error_toast);
        }
        
        // setup layout
        setContentView(R.layout.generic_layout_loading);
        TextView header = (TextView) findViewById(R.id.loading_header);
        header.setText(R.string.attachproject_login_loading);
        TextView subHeader = (TextView) findViewById(R.id.loading_subheader);
        subHeader.setVisibility(View.VISIBLE);
        subHeader.setText(url);
        
		// set up action bar
        actionBar = getSupportActionBar();
        actionBar.setDisplayHomeAsUpEnabled(true);
        
        // bind monitor service
        doBindService();
    }
    
	@Override
	protected void onResume() {
		// clear the password input from edittext
		// scenario: login fails and user navigates back to this activity
		try {
			EditText pwdInput = (EditText) findViewById(R.id.pwd_input);
			pwdInput.setText("");
		} catch (Exception e){} // catch exception because edittext does not exist upon first onResume.
		super.onResume();
	}

	@Override
	protected void onDestroy() {
    	if(Logging.VERBOSE) Log.v(Logging.TAG, "AttachProjectLoginActivity onDestroy");
	    doUnbindService();
	    super.onDestroy();
	}
	
	public void finish(Integer toastStringId){
		Toast toast = Toast.makeText(getApplicationContext(), toastStringId, Toast.LENGTH_LONG);
		toast.show();
		super.finish();
	}

	private void doBindService() {
	    // Establish a connection with the service, onServiceConnected gets called when
		bindService(new Intent(this, Monitor.class), mConnection, Service.BIND_AUTO_CREATE);
	}

	private void doUnbindService() {
	    if (mIsBound) {
	        // Detach existing connection.
	        unbindService(mConnection);
	        mIsBound = false;
	    }
	}
	
	// gets called by GetProjectConfig when ProjectConfig is available
	private void populateLayout(Boolean projectAlreadyAttached) {
		if(Logging.DEBUG) Log.d(Logging.TAG, "AttachProjectLoginActivity.populateLayout: projectAlreadyAttached: " + projectAlreadyAttached);
		
		setContentView(R.layout.attach_project_login_layout);
		
		// set name
        actionBar.setTitle(projectConfig.name);
		
		// set website
		TextView website = (TextView) findViewById(R.id.project_url);
    	SpannableString content = new SpannableString(projectConfig.masterUrl);
    	content.setSpan(new UnderlineSpan(), 0, content.length(), 0);
		website.setText(content);
		website.setOnClickListener(new OnClickListener(){
			@Override
			public void onClick(View v) {
	    		Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse(projectConfig.masterUrl));
	    		startActivity(i);
			}});
		
		// set android support
		TextView platform = (TextView) findViewById(R.id.project_platform);
		if(platformSupported()) {
			platform.setText(R.string.attachproject_login_platform_supported);
		} else {
			platform.setText(R.string.attachproject_login_platform_not_supported);
			platform.setBackgroundResource(R.drawable.shape_yellow_background);
			platform.setPadding(3, 3, 3, 3);
		}
		
		// set ProjectInfo fields, if ProjectInfo available (after list selection)
		if(projectInfoPresent) {
			// set layout wrapper visible
			LinearLayout projectInfoWrapper = (LinearLayout) findViewById(R.id.project_info);
			projectInfoWrapper.setVisibility(View.VISIBLE);
			
			// set project logo
			ImageView logo = (ImageView) findViewById(R.id.logo);
			if(projectLogo != null) {
				logo.setImageBitmap(projectLogo);
			} else {
				logo.setVisibility(View.GONE);
			}
			
			// set general area
			if(projectInfo.generalArea != null) {
				TextView generalArea = (TextView) findViewById(R.id.general_area);
				generalArea.setText(projectInfo.generalArea);
			} else {
				LinearLayout wrapper = (LinearLayout) findViewById(R.id.general_area_wrapper);
				wrapper.setVisibility(View.GONE);
			}
			
			// set specific area
			if(projectInfo.specificArea != null) {
				TextView specificArea = (TextView) findViewById(R.id.specific_area);
				specificArea.setText(projectInfo.specificArea);
			} else {
				LinearLayout wrapper = (LinearLayout) findViewById(R.id.specific_area_wrapper);
				wrapper.setVisibility(View.GONE);
			}
			
			// set description
			if(projectInfo.description != null) {
				TextView description = (TextView) findViewById(R.id.description);
				description.setText(projectInfo.description);
			} else {
				LinearLayout wrapper = (LinearLayout) findViewById(R.id.description_wrapper);
				wrapper.setVisibility(View.GONE);
			}
			
			// set home
			if(projectInfo.home != null) {
				TextView home = (TextView) findViewById(R.id.based_at);
				home.setText(projectInfo.home);
			} else {
				LinearLayout wrapper = (LinearLayout) findViewById(R.id.based_at_wrapper);
				wrapper.setVisibility(View.GONE);
			}
		}
		
		// terms of use
		if((projectConfig.termsOfUse != null) && (projectConfig.termsOfUse.length() > 0)) {
			LinearLayout termsOfUseWrapper = (LinearLayout) findViewById(R.id.terms_of_use_wrapper);
			termsOfUseWrapper.setVisibility(View.VISIBLE);
			TextView termsOfUseCategory = (TextView) findViewById(R.id.category_terms_of_use);
			termsOfUseCategory.setText(getString(R.string.attachproject_login_category_terms_of_use) + " " + projectConfig.name);
			TextView termsOfUseText = (TextView) findViewById(R.id.project_terms_of_use);
			termsOfUseText.setText(projectConfig.termsOfUse);
		}
		
		// set account creation
		if(projectAlreadyAttached) {
			LinearLayout creationWrapper = (LinearLayout) findViewById(R.id.creation_wrapper);
			creationWrapper.setVisibility(View.GONE);
		} else{
			TextView creationCategory = (TextView) findViewById(R.id.category_creation);
			creationCategory.setText(getString(R.string.attachproject_login_category_creation) + " " + projectConfig.name + "?");
			TextView creationText = (TextView) findViewById(R.id.creation_action);
			Button creationSubmit = (Button) findViewById(R.id.registration_button);
			if(projectConfig.accountCreationDisabled) {
				creationText.setText(R.string.attachproject_login_header_creation_disabled);
				creationSubmit.setVisibility(View.GONE);
			} else if(projectConfig.clientAccountCreationDisabled) {
				creationText.setText(R.string.attachproject_login_header_creation_client_disabled);
				creationSubmit.setTag(false);
			} else {
				// creation in client supported
				creationText.setText(R.string.attachproject_login_header_creation_enabled);
				creationSubmit.setTag(true);
			}
		}
		
		// set account login
		if(projectAlreadyAttached) {
			LinearLayout loginWrapper = (LinearLayout) findViewById(R.id.login_wrapper);
			loginWrapper.setVisibility(View.GONE);
		} else {
			TextView loginCategory = (TextView) findViewById(R.id.category_login);
			loginCategory.setText(R.string.attachproject_login_category_login);
			if(projectConfig.userName) { // user vs. email?
				if(Logging.DEBUG) Log.d(Logging.TAG,"project is using user name instead of email for login");
				TextView idHeader = (TextView) findViewById(R.id.header_id);
				idHeader.setText(R.string.attachproject_login_header_id_name);
				EditText idInput = (EditText) findViewById(R.id.id_input);
				idInput.setInputType(InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS);
			}
		}
		
		// set project attached
		if(projectAlreadyAttached) {
			LinearLayout attachedWrapper = (LinearLayout) findViewById(R.id.attached_wrapper);
			attachedWrapper.setVisibility(View.VISIBLE);
			TextView header = (TextView) attachedWrapper.findViewById(R.id.header);
			header.setText(R.string.attachproject_login_attached);
		}
	}
	
	public void login (View view) {
		// parse user input
		EditText idInput = (EditText) findViewById(R.id.id_input);
		EditText pwdInput = (EditText) findViewById(R.id.pwd_input);
		String id = idInput.getText().toString();
		String pwd = pwdInput.getText().toString();
		
		// verify input and start AttachProjectWorkingActivity
		if(verifyInput(id, pwd)){
			Intent intent = new Intent(this, AttachProjectWorkingActivity.class);
			intent.putExtra("action", AttachProjectWorkingActivity.ACTION_ATTACH);
			intent.putExtra("usesName", projectConfig.userName);
			intent.putExtra("projectUrl", getWebRpcUrl());
			intent.putExtra("projectName", projectConfig.name);
			intent.putExtra("id", id);
			intent.putExtra("pwd", pwd);
			startActivity(intent);
		}
	}
	
	// register button's onClick
	public void register (View view) {
		if(Logging.DEBUG) Log.d(Logging.TAG, "register: " + view.getTag());
		Boolean clientCreation = (Boolean) view.getTag();
		if (clientCreation) {
			// start intent to AttachProjectWorkingActivity
			Intent intent = new Intent(this, AttachProjectRegistrationActivity.class);
			intent.putExtra("projectUrl", getWebRpcUrl());
			intent.putExtra("projectName", projectConfig.name);
			intent.putExtra("minPwdLength", projectConfig.minPwdLength);
			intent.putExtra("usesName", projectConfig.userName);
			startActivity(intent);
		} else {
			// start intent to project website
			Intent i = new Intent(Intent.ACTION_VIEW);
			i.setData(Uri.parse(projectConfig.masterUrl));
			startActivity(i);
		}
	}
	
	//project forgot password button onClick
	public void forgotPasswd (View view) {
		// Start intent to project website forgot password link
		Intent i = new Intent(Intent.ACTION_VIEW);
		i.setData(Uri.parse(projectConfig.masterUrl + "/get_passwd.php"));
		startActivity(i);
	}
	
	// returns URL for web RPC (lookupAccount or createAccount)
	// HTTPS if supported (ProjectConfig.webRpcUrlBase), masterUrl if not
	private String getWebRpcUrl() {
		if(projectConfig.webRpcUrlBase.isEmpty()) return projectConfig.masterUrl;
		else {
			if(Logging.DEBUG) Log.d(Logging.TAG, "AttachProjectLoginActivity.getWebRpcUrl(): HTTPS URL found, using : " + projectConfig.webRpcUrlBase + " for further account related RPCs");
			return projectConfig.webRpcUrlBase;
		}
	}
	
	private Boolean verifyInput(String id, String pwd) {
		if(id.length() == 0) {
			Toast toast = Toast.makeText(getApplicationContext(), R.string.attachproject_error_no_name, Toast.LENGTH_SHORT);
			toast.show();
			return false;
		}
		if(pwd.length() == 0) {
			Toast toast = Toast.makeText(getApplicationContext(), R.string.attachproject_error_no_pwd, Toast.LENGTH_SHORT);
			toast.show();
			return false;
		}
		return true;
	}
	
	private Boolean platformSupported() {
		if(projectConfig == null) return false;
		if(!mIsBound) return false;
		String platformName = getString(monitor.getBoincPlatform());
		Boolean supported = false;
		for(PlatformInfo platform: projectConfig.platforms) {
			if(platform.name.equals(platformName)) {
				supported = true;
				continue;
			}
		}
		return supported;
	}
	
	private final class GetProjectConfig extends AsyncTask<String, Void, Integer> {
		
		private boolean projectAlreadyAttached = false;
		
		@Override
		protected Integer doInBackground(String... params) {
			String url = params[0];
			Integer attemptCounter = 0;
			Integer maxAttempts = getResources().getInteger(R.integer.attach_get_project_config_retries);
			try{
				while(attemptCounter < maxAttempts) {
					if(!projectInfoPresent) { // only url string is available
						if(Logging.DEBUG) Log.d(Logging.TAG, "doInBackground() - GetProjectConfig for manual input url: " + url);
						
						//fetch ProjectConfig
						projectConfig = monitor.clientInterface.getProjectConfigPolling(url);
					} else {
						if(Logging.DEBUG) Log.d(Logging.TAG, "doInBackground() - GetProjectConfig for list selection url: " + projectInfo.url);
						
						//fetch ProjectConfig
						projectConfig = monitor.clientInterface.getProjectConfigPolling(projectInfo.url);
						
						// fetch project logo	
						loadBitmap();
					}
					
					if (projectConfig != null && projectConfig.error_num != null && projectConfig.error_num == 0) {
						// success
						projectAlreadyAttached = checkProjectAlreadyAttached(url);
						return 0;
					} else { 
						if(Logging.DEBUG) if(projectConfig != null) Log.d(Logging.TAG,"getProjectConfig returned error num:" + projectConfig.error_num);
						attemptCounter++;
					}
				}
			} catch(Exception e) {
				if(Logging.WARNING) Log.w(Logging.TAG,"error in doInBackround",e);
			}
			// if this code is reached, it failed, return
			if(Logging.DEBUG) if(projectConfig != null) Log.d(Logging.TAG,"getProjectConfig returned error num:" + projectConfig.error_num);
			return R.string.attachproject_login_error_toast;
		}
		
		@Override
		protected void onPostExecute(Integer toastStringId) {
			if(toastStringId == 0) { // no error, no toast...
				if(Logging.DEBUG) Log.d(Logging.TAG, "onPostExecute() - GetProjectConfig successful.");
				populateLayout(projectAlreadyAttached);
			} else {
				finish(toastStringId);
			}
			
		}
		
		private void loadBitmap() {
			projectLogo = null;
			try {
				URL logoUrlUrl = new URL(projectInfo.imageUrl);
				projectLogo = BitmapFactory.decodeStream(logoUrlUrl.openConnection().getInputStream());
				if(projectLogo!=null) if(Logging.DEBUG) Log.d(Logging.TAG, "logo download successful.");
			} catch (Exception e) {
				if(Logging.WARNING) Log.w(Logging.TAG,"loadBitmap failed.",e);
			}
		}
		
		private Boolean checkProjectAlreadyAttached(String url) {
			if(Logging.DEBUG) Log.d(Logging.TAG, "check whether project with url is already attached: " + url);
			return monitor.clientInterface.checkProjectAttached(url);
		}
	}
}
